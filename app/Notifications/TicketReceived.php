<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Support\SiteData;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tanda terima untuk pelapor. Sengaja tidak memuat isi aduan
 * agar informasi insiden tidak ikut tersebar lewat email.
 */
class TicketReceived extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[' . $this->ticket->ticket_number . '] Aduan Anda telah diterima')
            // Jangan masukkan input pelapor (nama, dll.) ke email: isinya dirender sebagai
            // Markdown dan alamat tujuan diisi pelapor sendiri, sehingga bisa disalahgunakan
            // untuk menyisipkan tautan phishing ke email resmi CSIRT.
            ->greeting('Yth. Pelapor,')
            ->line('Terima kasih, aduan insiden siber Anda telah kami terima dengan nomor tiket:')
            ->line('**' . $this->ticket->ticket_number . '**')
            ->line('Simpan nomor tiket ini. Status penanganan dapat dicek menggunakan nomor tiket dan alamat email Anda.')
            ->action('Cek Status Aduan', route('tickets.status.form'))
            ->salutation('Salam, ' . SiteData::name());
    }
}
