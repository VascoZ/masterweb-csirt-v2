<?php

namespace App\Enums;

enum TicketStatus: string
{
    case New = 'new';
    case Verified = 'verified';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Verified => 'Terverifikasi',
            self::InProgress => 'Sedang Ditangani',
            self::Resolved => 'Selesai',
            self::Rejected => 'Ditolak',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'badge-soft-primary',
            self::Verified => 'badge-soft-info',
            self::InProgress => 'badge-soft-warning',
            self::Resolved => 'badge-soft-success',
            self::Rejected => 'badge-soft-secondary',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Resolved, self::Rejected], true);
    }
}
