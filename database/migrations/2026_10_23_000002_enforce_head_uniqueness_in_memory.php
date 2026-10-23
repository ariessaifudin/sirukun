<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        if (DB::getDriverName() === 'sqlite') DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS satu_kepala_aktif ON warga(kartu_keluarga_id) WHERE hubungan_keluarga='kepala_keluarga' AND status='aktif' AND deleted_at IS NULL");
    }
    public function down(): void {
        if (DB::getDriverName() === 'sqlite') DB::statement('DROP INDEX IF EXISTS satu_kepala_aktif');
    }
};
