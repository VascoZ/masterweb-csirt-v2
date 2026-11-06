<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga konsistensi antara konfigurasi hardening dan konfigurasi deployment.
 * Kesalahan jenis ini tidak tertangkap test aplikasi karena baru muncul di container.
 */
class DeploymentConfigTest extends TestCase
{
    public function test_docker_scheduler_does_not_rely_on_disabled_proc_open(): void
    {
        $ini = file_get_contents(base_path('docker/php/security.ini'));
        $compose = file_get_contents(base_path('docker-compose.yml'));

        $this->assertMatchesRegularExpression('/^disable_functions\s*=.*\bproc_open\b/m', $ini, 'Hardening PHP seharusnya menonaktifkan proc_open');

        // schedule:work menjalankan sub-proses lewat proc_open → container scheduler crash-loop.
        $scheduler = substr($compose, (int) strpos($compose, '  scheduler:'), 1600);
        $this->assertStringNotContainsString('schedule:work', preg_replace('/^\s*#.*$/m', '', $scheduler));
        $this->assertStringContainsString('schedule:run', $scheduler);
    }

    public function test_scheduled_tasks_are_closures_that_need_no_subprocess(): void
    {
        // Loop schedule:run hanya aman bila tidak ada tugas bertipe perintah/shell.
        $console = file_get_contents(base_path('routes/console.php'));

        $this->assertStringNotContainsString('Schedule::command(', $console);
        $this->assertStringNotContainsString('Schedule::exec(', $console);
    }

    public function test_install_script_and_docker_agree_on_php_version(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $install = file_get_contents(base_path('scripts/install-ubuntu.sh'));
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        preg_match('/^FROM php:(\d+\.\d+)-fpm/m', $dockerfile, $docker);
        preg_match('/^PHP_VERSION=(\d+\.\d+)/m', $install, $script);

        $this->assertSame($docker[1], $script[1], 'Versi PHP Dockerfile dan script instalasi harus sama');
        $this->assertSame('^' . $docker[1], $composer['require']['php']);
    }
}
