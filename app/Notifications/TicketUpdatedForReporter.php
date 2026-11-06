<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Support\SiteData;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan ke pelapor saat status aduan berubah atau ada catatan publik baru.
 * Isi catatan tidak disertakan langsung di email agar detail insiden tetap terlindungi.
 */
class TicketUpdatedForReporter extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket, public bool $hasPublicNote = false)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[' . $this->ticket->ticket_number . '] Pembaruan status aduan: ' . $this->ticket->status->label())
            // Tanpa input pelapor (lihat catatan di TicketReceived).
            ->greeting('Yth. Pelapor,')
            ->line('Terdapat pembaruan pada aduan insiden siber Anda dengan nomor tiket **' . $this->ticket->ticket_number . '**.')
            ->line('Status saat ini: **' . $this->ticket->status->label() . '**');

        if ($this->hasPublicNote) {
            $mail->line('Tim kami telah menambahkan keterangan baru yang dapat Anda baca melalui halaman Cek Status Aduan.');
        }

        return $mail
            ->action('Cek Status Aduan', route('tickets.status.form'))
            ->salutation('Salam, ' . SiteData::name());
    }
}
