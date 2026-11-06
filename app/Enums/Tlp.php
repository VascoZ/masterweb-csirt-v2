<?php

namespace App\Enums;

/**
 * Traffic Light Protocol 2.0 (FIRST).
 */
enum Tlp: string
{
    case Clear = 'CLEAR';
    case Green = 'GREEN';
    case Amber = 'AMBER';
    case AmberStrict = 'AMBER+STRICT';
    case Red = 'RED';

    public function label(): string
    {
        return 'TLP:' . $this->value;
    }

    public function description(): string
    {
        return match ($this) {
            self::Clear => 'Boleh disebarluaskan tanpa batasan.',
            self::Green => 'Terbatas untuk komunitas; tidak untuk publikasi umum.',
            self::Amber => 'Terbatas untuk organisasi penerima dan klien yang perlu mengetahui.',
            self::AmberStrict => 'Terbatas hanya untuk organisasi penerima.',
            self::Red => 'Hanya untuk penerima yang hadir/dituju, tidak boleh diteruskan.',
        };
    }

    /**
     * Kelas warna sesuai spesifikasi warna TLP 2.0.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Clear => 'tlp-clear',
            self::Green => 'tlp-green',
            self::Amber, self::AmberStrict => 'tlp-amber',
            self::Red => 'tlp-red',
        };
    }

    /**
     * Hanya TLP:CLEAR yang boleh tampil di halaman publik.
     */
    public function isPublic(): bool
    {
        return $this === self::Clear;
    }
}
