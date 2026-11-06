<?php

namespace App\Support;

use App\Models\TaskRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * Registri dan pelaksana tugas yang boleh dijalankan dari dashboard/scheduler.
 * Hanya tugas yang terdaftar di sini yang bisa dijalankan (allow-list); nama
 * perintah tidak pernah berasal dari input pengguna.
 */
class TaskRunner
{
    public const HEARTBEAT_KEY = 'csirt.scheduler.heartbeat';

    /**
     * @return array<string, array{label: string, command: string, description: string, dry_run: bool, enabled_setting: string, time_setting: string}>
     */
    public static function definitions(): array
    {
        return [
            'prune' => [
                'label' => 'Retensi data',
                'command' => 'csirt:prune',
                'description' => 'Menghapus audit log yang melewati masa simpan dan menganonimkan data pribadi aduan yang sudah lama ditutup, sesuai kebijakan retensi.',
                'dry_run' => true,
                'enabled_setting' => 'task_prune_enabled',
                'time_setting' => 'task_prune_time',
            ],
        ];
    }

    public static function exists(string $task): bool
    {
        return isset(self::definitions()[$task]);
    }

    /**
     * Jalankan tugas dan catat hasilnya. Tidak melempar exception: kegagalan
     * dicatat sebagai status "failed".
     */
    public static function run(string $task, string $trigger, bool $dryRun = false, ?int $userId = null): TaskRun
    {
        $definition = self::definitions()[$task] ?? throw new \InvalidArgumentException("Tugas tidak dikenal: {$task}");
        $startedAt = now();
        $buffer = new BufferedOutput();
        $status = 'success';

        try {
            $exit = Artisan::call($definition['command'], $dryRun && $definition['dry_run'] ? ['--dry-run' => true] : [], $buffer);
            $status = $exit === 0 ? 'success' : 'failed';
        } catch (Throwable $e) {
            $status = 'failed';
            $buffer->writeln('Galat: ' . $e->getMessage());
        }

        return TaskRun::create([
            'task' => $task,
            'trigger' => $trigger,
            'status' => $status,
            'dry_run' => $dryRun,
            // Buang kode warna ANSI dan batasi ukuran agar tidak membengkakkan tabel.
            'output' => mb_substr(trim(preg_replace('/\e\[[0-9;]*m/', '', $buffer->fetch())), 0, 4000),
            'user_id' => $userId,
            'started_at' => $startedAt,
            'finished_at' => now(),
        ]);
    }

    public static function beat(): void
    {
        Cache::put(self::HEARTBEAT_KEY, now()->timestamp, now()->addDay());
    }

    /**
     * Umur detak terakhir scheduler dalam detik, atau null bila belum pernah berjalan.
     */
    public static function heartbeatAge(): ?int
    {
        $last = Cache::get(self::HEARTBEAT_KEY);

        return $last ? max(0, now()->timestamp - (int) $last) : null;
    }
}
