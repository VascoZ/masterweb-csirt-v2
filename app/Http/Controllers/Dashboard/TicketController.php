<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\IncidentCategory;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Notifications\TicketUpdatedForReporter;
use App\Support\AuditLogger;
use App\Support\TicketMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $status = TicketStatus::tryFrom((string) $request->query('status'));
        $category = IncidentCategory::tryFrom((string) $request->query('category'));

        return view('dashboard.tickets.index', [
            'status' => $status,
            'category' => $category,
            'tickets' => Ticket::query()
                ->with('assignee')
                ->when($status, fn ($q) => $q->where('status', $status->value))
                ->when($category, fn ($q) => $q->where('category', $category->value))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $search = '%' . $request->string('search') . '%';
                    $q->where(fn ($q) => $q->where('ticket_number', 'like', $search)
                        ->orWhere('reporter_name', 'like', $search)
                        ->orWhere('reporter_email', 'like', $search)
                        ->orWhere('reporter_organization', 'like', $search));
                })
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        return view('dashboard.tickets.show', [
            'ticket' => $ticket->load(['attachments', 'updates.user', 'assignee']),
            'staff' => User::where('is_active', true)
                ->where(fn ($q) => $q->where('is_admin', true)->orWhere('is_superadmin', true))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
            // Hanya petugas aktif (admin/superadmin), sama dengan pilihan di dropdown.
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_active', true)
                ->where(fn ($q) => $q->where('is_admin', true)->orWhere('is_superadmin', true)))],
            'note' => ['nullable', 'string', 'max:5000', 'required_if_accepted:is_public'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $newStatus = TicketStatus::from($data['status']);
        $statusChanged = $ticket->status !== $newStatus;
        $isPublicNote = $request->boolean('is_public') && filled($data['note'] ?? null);

        $ticket->status = $newStatus;
        $ticket->assigned_to = $data['assigned_to'] ?? null;
        $ticket->closed_at = $newStatus->isClosed() ? ($ticket->closed_at ?? now()) : null;
        $ticket->save();

        if ($statusChanged || filled($data['note'] ?? null)) {
            $ticket->updates()->create([
                'user_id' => $request->user()->id,
                'status' => $statusChanged ? $newStatus : null,
                'note' => $data['note'] ?? null,
                'is_public' => $request->boolean('is_public'),
            ]);
        }

        // Hanya terkirim bila CSIRT_MAIL_NOTIFICATIONS=true (default nonaktif).
        if ($statusChanged || $isPublicNote) {
            TicketMailer::toReporter($ticket, new TicketUpdatedForReporter($ticket, $isPublicNote));
        }

        return redirect()->route('dashboard.tickets.show', $ticket)->with('success', 'Tiket berhasil diperbarui.');
    }

    public function download(Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $ticket);
        abort_unless((int) $attachment->ticket_id === (int) $ticket->id, 404);

        AuditLogger::log('attachment_downloaded', $ticket, description: 'Unduh lampiran ' . $attachment->original_name . ' (' . $ticket->ticket_number . ')');

        // Selalu sebagai unduhan (bukan inline) agar file bukti tidak dieksekusi browser.
        return Storage::disk('local')->download($attachment->path, $attachment->original_name, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * Ekspor daftar aduan ke CSV (sesuai filter aktif).
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $status = TicketStatus::tryFrom((string) $request->query('status'));
        $category = IncidentCategory::tryFrom((string) $request->query('category'));

        $tickets = Ticket::query()
            ->with('assignee')
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($category, fn ($q) => $q->where('category', $category->value))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%' . $request->string('search') . '%';
                $q->where(fn ($q) => $q->where('ticket_number', 'like', $search)
                    ->orWhere('reporter_name', 'like', $search)
                    ->orWhere('reporter_email', 'like', $search)
                    ->orWhere('reporter_organization', 'like', $search));
            })
            ->latest()
            ->get();

        AuditLogger::log('tickets_exported', description: 'Ekspor CSV daftar aduan (' . $tickets->count() . ' tiket)');

        $sanitize = function (?string $val): string {
            $val = (string) $val;

            return ($val !== '' && str_contains("=+-@\t\r", $val[0])) ? "'" . $val : $val;
        };

        $filename = 'aduan-csirt-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($tickets, $sanitize) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nomor Tiket', 'Status', 'TLP', 'Kategori', 'Pelapor', 'Email', 'Instansi', 'Aset Terdampak', 'Petugas', 'Waktu Lapor', 'Waktu Selesai']);

            foreach ($tickets as $t) {
                fputcsv($out, [
                    $t->ticket_number,
                    $t->status->label(),
                    $t->tlp->label(),
                    $t->category->label(),
                    $sanitize($t->reporter_name),
                    $sanitize($t->reporter_email),
                    $sanitize($t->reporter_organization),
                    $sanitize($t->affected_asset),
                    $sanitize($t->assignee?->name),
                    $t->created_at?->format('Y-m-d H:i:s'),
                    $t->closed_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
