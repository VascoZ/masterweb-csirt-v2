<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Ticket;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan kebijakan retensi data (config/csirt.php → retention):
 *  - menghapus audit log yang melewati masa simpan (NIST SP 800-53 AU-11);
 *  - menganonimkan aduan yang sudah lama ditutup: data pribadi pelapor, kronologi,
 *    catatan, dan lampiran dihapus; nomor tiket, kategori, status, dan tanggal
 *    tetap disimpan untuk statistik (UU PDP: pembatasan penyimpanan).
 * Dijadwalkan harian (routes/console.php).
 */
class PruneCommand extends Command
{
    protected $signature = 'csirt:prune {--dry-run : Tampilkan jumlah data yang akan diproses tanpa mengubah apa pun}';

    protected $description = 'Terapkan kebijakan retensi data (audit log & data pribadi aduan)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $auditDays = (int) config('csirt.retention.audit_log_days');
        $ticketDays = (int) config('csirt.retention.closed_ticket_days');
        $summary = [];

        if ($auditDays > 0) {
            $query = AuditLog::query()->where('created_at', '<', now()->subDays($auditDays));
            $count = (clone $query)->count();
            // Penghapusan lewat query builder (bukan per model) adalah satu-satunya jalur
            // penghapusan audit log yang disengaja; model AuditLog sendiri menolak delete().
            if (! $dryRun && $count) {
                $query->delete();
            }
            $summary['audit_log_dihapus'] = $count;
            $this->components->twoColumnDetail("Audit log > {$auditDays} hari", (string) $count);
        } else {
            $this->components->twoColumnDetail('Retensi audit log', '<fg=yellow>nonaktif</>');
        }

        if ($ticketDays > 0) {
            $tickets = Ticket::query()
                ->whereNull('anonymized_at')
                ->whereNotNull('closed_at')
                ->where('closed_at', '<', now()->subDays($ticketDays));
            $count = (clone $tickets)->count();

            if (! $dryRun) {
                $tickets->with('attachments')->chunkById(100, function ($chunk) {
                    foreach ($chunk as $ticket) {
                        DB::transaction(fn () => $this->anonymize($ticket));
                    }
                });
            }
            $summary['aduan_dianonimkan'] = $count;
            $this->components->twoColumnDetail("Aduan ditutup > {$ticketDays} hari", (string) $count);
        } else {
            $this->components->twoColumnDetail('Retensi data pribadi aduan', '<fg=yellow>nonaktif</>');
        }

        if (! $dryRun && array_sum($summary) > 0) {
            AuditLogger::log('data_pruned', null, null, $summary, 'Retensi data dijalankan');
        }

        if ($dryRun) {
            $this->components->info('Mode --dry-run: tidak ada data yang diubah.');
        }

        return self::SUCCESS;
    }

    private function anonymize(Ticket $ticket): void
    {
        // Menghapus model lampiran juga menghapus file buktinya (lihat TicketAttachment).
        $ticket->attachments->each->delete();
        $ticket->updates()->whereNotNull('note')->update(['note' => '[dihapus sesuai kebijakan retensi]']);

        $ticket->forceFill([
            'reporter_name' => '[dihapus]',
            'reporter_email' => 'anonim@invalid',
            'reporter_phone' => null,
            'reporter_organization' => null,
            'reporter_ip' => null,
            'affected_asset' => null,
            'description' => '[dihapus sesuai kebijakan retensi]',
            'anonymized_at' => now(),
        ])->save();
    }
}
