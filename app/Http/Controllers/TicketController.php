<?php

namespace App\Http\Controllers;

use App\Enums\IncidentCategory;
use App\Enums\Tlp;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;
use App\Notifications\TicketReceived;
use App\Notifications\TicketSubmittedToTeam;
use App\Support\TicketMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Form aduan insiden siber untuk publik dan pengecekan status tiket.
 */
class TicketController extends Controller
{
    public function create(): View
    {
        return view('pages.tickets.create', [
            'categories' => IncidentCategory::cases(),
            'tlps' => Tlp::cases(),
        ]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['attachments', 'agreement', 'captcha']);

        $ticket = DB::transaction(function () use ($data, $request) {
            $ticket = new Ticket($data);
            $ticket->reporter_ip = $request->ip();
            $ticket->save();

            foreach ($request->file('attachments', []) as $file) {
                $ticket->attachments()->create([
                    'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    // Disimpan di disk privat dengan nama acak; hanya admin yang bisa mengunduh.
                    'path' => $file->store('tickets/' . $ticket->id, 'local'),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                ]);
            }

            $ticket->updates()->create(['status' => $ticket->status, 'note' => 'Aduan diterima melalui situs.', 'is_public' => true]);

            return $ticket;
        });

        $this->notify($ticket);

        return redirect()->route('tickets.submitted')->with('ticket_number', $ticket->ticket_number);
    }

    public function submitted(Request $request): View|RedirectResponse
    {
        $number = $request->session()->get('ticket_number');

        return $number ? view('pages.tickets.submitted', ['number' => $number]) : redirect()->route('tickets.create');
    }

    public function statusForm(): View
    {
        return view('pages.tickets.status');
    }

    public function status(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'ticket_number' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email'],
        ]);

        $ticket = Ticket::findForReporter($data['ticket_number'], $data['email']);

        if (! $ticket) {
            return back()->withInput()->with('error', 'Tiket tidak ditemukan. Periksa kembali nomor tiket dan email pelapor.');
        }

        return view('pages.tickets.status', [
            'ticket' => $ticket->load('publicUpdates'),
        ]);
    }

    /**
     * Balasan atau keterangan tambahan dari pelapor pada halaman cek status.
     */
    public function reply(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'ticket_number' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email'],
            'note' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $ticket = Ticket::findForReporter($data['ticket_number'], $data['email']);

        if (! $ticket) {
            return redirect()->route('tickets.status.form')->with('error', 'Tiket tidak ditemukan.');
        }

        if ($ticket->status->isClosed()) {
            return redirect()->route('tickets.status.form')->with('error', 'Tiket sudah ditutup dan tidak dapat menerima keterangan tambahan.');
        }

        $ticket->updates()->create([
            'user_id' => null,
            'status' => null,
            'note' => '[Pelapor] ' . $data['note'],
            'is_public' => true,
        ]);

        TicketMailer::toTeam($ticket, new TicketSubmittedToTeam($ticket));

        session()->flash('success', 'Keterangan tambahan berhasil dikirim.');

        return view('pages.tickets.status', [
            'ticket' => $ticket->load('publicUpdates'),
        ]);
    }

    /**
     * Email hanya terkirim bila CSIRT_MAIL_NOTIFICATIONS=true (default nonaktif).
     */
    private function notify(Ticket $ticket): void
    {
        TicketMailer::toReporter($ticket, new TicketReceived($ticket));
        TicketMailer::toTeam($ticket, new TicketSubmittedToTeam($ticket));
    }
}
