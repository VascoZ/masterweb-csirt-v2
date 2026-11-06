<?php

use App\Support\Settings;
use App\Support\TaskRunner;
use Illuminate\Support\Facades\Schedule;

/*
| Tugas terjadwal. Dijalankan oleh cron `php artisan schedule:run` (on-premise,
| dipasang install-ubuntu.sh) atau service "scheduler" di docker-compose.yml.
|
| Aktif/nonaktif dan jam eksekusi dibaca dari Pengaturan Sistem (dashboard,
| superadmin) pada setiap menit, jadi perubahan berlaku tanpa restart.
*/

// Detak jantung: dashboard memakainya untuk menampilkan apakah scheduler benar-benar berjalan.
Schedule::call(fn () => TaskRunner::beat())->name('csirt-heartbeat')->everyMinute();

foreach (TaskRunner::definitions() as $task => $definition) {
    Schedule::call(fn () => TaskRunner::run($task, 'schedule'))
        ->name($definition['command'])
        ->dailyAt((string) Settings::get($definition['time_setting']))
        ->when(fn () => Settings::bool($definition['enabled_setting']))
        ->withoutOverlapping()
        ->onOneServer();
}
