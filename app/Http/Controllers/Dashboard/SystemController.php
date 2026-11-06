<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\TaskRun;
use App\Support\AuditLogger;
use App\Support\Settings;
use App\Support\SystemStatus;
use App\Support\TaskRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengaturan Sistem (khusus superadmin): status, tugas terjadwal, dan pengaturan
 * fitur. Rute dilindungi `can:superadmin`; otorisasi diperiksa ulang di sini.
 */
class SystemController extends Controller
{
    public function index(): View
    {
        $this->authorize('superadmin');

        $tasks = collect(TaskRunner::definitions())->map(function (array $definition, string $key) {
            $enabled = Settings::bool($definition['enabled_setting']);
            $time = (string) Settings::get($definition['time_setting']);
            $next = now()->setTimeFromTimeString($time);
            if ($next->isPast()) {
                $next->addDay();
            }

            return $definition + [
                'key' => $key,
                'enabled' => $enabled,
                'time' => $time,
                'next' => $enabled ? $next : null,
                'last' => TaskRun::where('task', $key)->where('dry_run', false)->latest('started_at')->first(),
            ];
        });

        return view('dashboard.system.index', [
            'checks' => SystemStatus::checks(),
            'tasks' => $tasks,
            'runs' => TaskRun::with('user')->latest('started_at')->take(10)->get(),
            'definitions' => Settings::DEFINITIONS,
            'values' => collect(Settings::DEFINITIONS)->map(fn ($d, $key) => Settings::get($key))->all(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorize('superadmin');

        $data = $request->validate(Settings::rules());

        $changes = Settings::save($data, $request->user()->id);

        if ($changes['new'] === []) {
            return back()->with('success', 'Tidak ada perubahan.');
        }

        AuditLogger::log('settings_updated', null, $changes['old'], $changes['new'], 'Pengaturan sistem diubah: ' . implode(', ', array_keys($changes['new'])));

        $notice = 'Pengaturan disimpan.';
        if (($changes['new']['mail_notifications'] ?? false) === true && config('mail.default') === 'log') {
            $notice .= ' Perhatian: MAIL_MAILER masih "log", email belum benar-benar terkirim. Isi SMTP di .env.';
        }

        return back()->with('success', $notice);
    }

    public function runTask(Request $request, string $task): RedirectResponse
    {
        $this->authorize('superadmin');
        abort_unless(TaskRunner::exists($task), 404);

        $dryRun = $request->boolean('dry_run');
        $run = TaskRunner::run($task, 'manual', $dryRun, $request->user()->id);

        AuditLogger::log('task_run_manual', null, null, ['task' => $task, 'dry_run' => $dryRun, 'status' => $run->status],
            'Tugas "' . $task . '" dijalankan manual' . ($dryRun ? ' (simulasi)' : ''));

        return back()
            ->with($run->succeeded() ? 'success' : 'error', ($dryRun ? 'Simulasi ' : 'Tugas ') . (TaskRunner::definitions()[$task]['label']) . ($run->succeeded() ? ' selesai.' : ' gagal.'))
            ->with('task_output', $run->output);
    }
}
