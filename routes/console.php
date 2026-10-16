<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
Artisan::command('sirukun:reminders',function(){$this->info(App\Services\Reminders::run().' notifikasi baru dibuat.');});
Artisan::command('sirukun:backup',function(){$id=App\Services\Backup::create();$this->info('Backup selesai, ID '.$id);});
Schedule::command('sirukun:reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('sirukun:backup')->dailyAt('01:00')->withoutOverlapping();
