<?php
namespace App\Database;

use PDO;
use RuntimeException;

/** Logical SQL database serialized entirely in RAM; never writes a plaintext dump. */
final class Snapshot
{
    public static function memory(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA temp_store=MEMORY; PRAGMA journal_mode=MEMORY; PRAGMA foreign_keys=ON');
        return $pdo;
    }

    public static function encode(PDO $pdo): string
    {
        $schema = $pdo->query("SELECT type, name, sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END, name")->fetchAll(PDO::FETCH_ASSOC);
        $tables = [];
        foreach ($schema as $item) {
            if ($item['type'] !== 'table') continue;
            $name = $item['name'];
            $tables[$name] = $pdo->query('SELECT * FROM '.self::quote($name))->fetchAll(PDO::FETCH_ASSOC);
        }
        $sequences = [];
        if ($pdo->query("SELECT count(*) FROM sqlite_master WHERE name='sqlite_sequence'")->fetchColumn()) {
            $sequences = $pdo->query('SELECT name, seq FROM sqlite_sequence')->fetchAll(PDO::FETCH_ASSOC);
        }
        return json_encode(['version' => 1, 'schema' => $schema, 'tables' => $tables, 'sequences' => $sequences], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public static function decode(string $json): PDO
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (($data['version'] ?? null) !== 1 || !is_array($data['schema'] ?? null) || !is_array($data['tables'] ?? null)) {
            throw new RuntimeException('Snapshot database tidak valid.');
        }
        $pdo = self::memory();
        $pdo->exec('PRAGMA foreign_keys=OFF');
        $pdo->beginTransaction();
        foreach ($data['schema'] as $item) {
            if ($item['type'] === 'table') $pdo->exec($item['sql']);
        }
        foreach ($data['tables'] as $table => $rows) self::insertRows($pdo, $table, $rows);
        if ($data['sequences']) {
            $pdo->exec('DELETE FROM sqlite_sequence');
            self::insertRows($pdo, 'sqlite_sequence', $data['sequences']);
        }
        foreach ($data['schema'] as $item) {
            if ($item['type'] !== 'table') $pdo->exec($item['sql']);
        }
        if ($pdo->query('PRAGMA foreign_key_check')->fetch()) throw new RuntimeException('Relasi pada snapshot tidak valid.');
        $pdo->commit();
        $pdo->exec('PRAGMA foreign_keys=ON');
        return $pdo;
    }

    public static function insertRows(PDO $pdo, string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $sql = 'INSERT INTO '.self::quote($table).' ('.implode(',', array_map(self::quote(...), array_keys($row))).') VALUES ('.implode(',', array_fill(0, count($row), '?')).')';
            $pdo->prepare($sql)->execute(array_values($row));
        }
    }

    public static function quote(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
}
