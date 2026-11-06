<?php

namespace App\Models;

use App\Enums\IncidentCategory;
use App\Enums\TicketStatus;
use App\Enums\Tlp;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'reporter_name', 'reporter_email', 'reporter_phone', 'reporter_organization',
        'category', 'incident_at', 'affected_asset', 'description', 'tlp',
    ];

    protected function casts(): array
    {
        return [
            'category' => IncidentCategory::class,
            'status' => TicketStatus::class,
            'tlp' => Tlp::class,
            'incident_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            $ticket->ticket_number ??= static::generateNumber();
            $ticket->status ??= TicketStatus::New;
        });
    }

    /**
     * Format: PREFIX-YYYYMMDD-XXXXXX (6 karakter acak tanpa huruf/angka yang mirip).
     */
    public static function generateNumber(): string
    {
        do {
            $random = collect(range(1, 6))
                ->map(fn () => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)])
                ->implode('');
            $number = sprintf('%s-%s-%s', config('csirt.ticket_prefix'), now()->format('Ymd'), $random);
        } while (static::where('ticket_number', $number)->exists());

        return $number;
    }

    public function getRouteKeyName(): string
    {
        return 'ticket_number';
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TicketUpdate::class)->latest();
    }

    public function publicUpdates(): HasMany
    {
        return $this->updates()->where('is_public', true);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Rejected->value]);
    }

    /**
     * Pencocokan nomor tiket + email pelapor untuk cek status publik.
     */
    public static function findForReporter(string $number, string $email): ?self
    {
        return static::where('ticket_number', Str::upper(trim($number)))
            ->whereRaw('LOWER(reporter_email) = ?', [Str::lower(trim($email))])
            ->first();
    }

    public function auditLabel(): string
    {
        return $this->ticket_number;
    }
}
