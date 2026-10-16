<?php
define('LARAVEL_START', microtime(true));
if (!is_file(__DIR__.'/../storage/app/installed.lock')) {
    header('Location: install.php'); exit;
}
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) require $maintenance;
require __DIR__.'/../vendor/autoload.php';
(require_once __DIR__.'/../bootstrap/app.php')->handleRequest(Illuminate\Http\Request::capture());
