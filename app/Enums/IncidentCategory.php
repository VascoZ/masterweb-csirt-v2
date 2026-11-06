<?php

namespace App\Enums;

enum IncidentCategory: string
{
    case Defacement = 'defacement';
    case Malware = 'malware';
    case Ransomware = 'ransomware';
    case Phishing = 'phishing';
    case Ddos = 'ddos';
    case DataLeak = 'data_leak';
    case UnauthorizedAccess = 'unauthorized_access';
    case Vulnerability = 'vulnerability';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Defacement => 'Web Defacement',
            self::Malware => 'Malware',
            self::Ransomware => 'Ransomware',
            self::Phishing => 'Phishing / Penipuan Daring',
            self::Ddos => 'DoS / DDoS',
            self::DataLeak => 'Kebocoran Data',
            self::UnauthorizedAccess => 'Akses Ilegal / Peretasan Akun',
            self::Vulnerability => 'Laporan Kerentanan',
            self::Other => 'Lainnya',
        };
    }
}
