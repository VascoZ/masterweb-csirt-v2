<?php

namespace App\Enums;

enum Severity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Medium => 'Sedang',
            self::High => 'Tinggi',
            self::Critical => 'Kritis',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Low => 'badge-soft-secondary',
            self::Medium => 'badge-soft-info',
            self::High => 'badge-soft-warning',
            self::Critical => 'text-bg-danger',
        };
    }

    /**
     * Kelas aksen garis kiri pada daftar advisory publik.
     */
    public function accentClass(): string
    {
        return 'sev-' . $this->value;
    }
}
