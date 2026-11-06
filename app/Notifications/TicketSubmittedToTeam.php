<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan ke tim. Detail aduan hanya bisa dibaca di dashboard.
 */
class TicketSubmittedToTeam extends Notification
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
            ->subject('[' . $this->ticket->ticket_number . '] Aduan baru: ' . $this->ticket->category->label())
            ->line('Aduan insiden baru masuk dengan klasifikasi ' . $this->ticket->tlp->label() . '.')
            ->line('Jenis: ' . $this->ticket->category->label())
            ->action('Buka di Dashboard', route('dashboard.tickets.show', $this->ticket));
    }
}
