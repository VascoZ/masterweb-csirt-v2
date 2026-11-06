<?php

namespace App\Support;

use App\Models\Ticket;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Satu-satunya jalur pengiriman email terkait aduan.
 *
 * Default NONAKTIF (CSIRT_MAIL_NOTIFICATIONS=false): aduan dipantau, dibaca, dan
 * ditindaklanjuti sepenuhnya melalui dashboard; pelapor memantau lewat halaman
 * Cek Status. Aktifkan setelah SMTP dikonfigurasi.
 */
class TicketMailer
{
    public static function enabled(): bool
    {
        return (bool) config('csirt.mail_notifications');
    }

    /**
     * Kirim email ke pelapor tiket.
     */
    public static function toReporter(Ticket $ticket, BaseNotification $notification): void
    {
        self::send($ticket->reporter_email, $notification, $ticket);
    }

    /**
     * Kirim email ke alamat tim (CSIRT_TEAM_EMAIL), bila diisi.
     */
    public static function toTeam(Ticket $ticket, BaseNotification $notification): void
    {
        if ($team = config('csirt.team_email')) {
            self::send($team, $notification, $ticket);
        }
    }

    private static function send(?string $address, BaseNotification $notification, Ticket $ticket): void
    {
        if (! self::enabled() || blank($address)) {
            return;
        }

        try {
            Notification::route('mail', $address)->notify($notification);
        } catch (Throwable $e) {
            // Aduan tetap tersimpan walau email gagal terkirim.
            Log::warning('Notifikasi email aduan gagal dikirim', [
                'ticket' => $ticket->ticket_number,
                'notification' => class_basename($notification),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
