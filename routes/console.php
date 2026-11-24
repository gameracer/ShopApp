<?php

use App\Services\BackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prints the new backup's path as the last line (the desktop app reads it), or nothing when skipped.
Artisan::command('backup:run {--if-due : Only back up if a scheduled backup is due} {--on-close : Backup when the desktop app closes, if turned on in Settings} {--label=manual : Word added to the file name}', function (BackupService $backups) {
    try {
        $path = match (true) {
            $this->option('if-due') => $backups->runIfDue(),
            $this->option('on-close') => $backups->runOnClose(),
            default => $backups->run($this->option('label')),
        };
    } catch (RuntimeException $e) {
        $this->error('Backup failed: '.$e->getMessage());

        return 1;
    }

    if ($path) {
        $this->line($path);
    }

    return 0;
})->purpose('Back up the shop database');

// On a server, add the Laravel scheduler to cron (* * * * * php artisan schedule:run).
// The desktop app calls backup:run --if-due itself (desktop/main.js).
Schedule::command('backup:run --if-due')->everyMinute()->withoutOverlapping();
