<?php

namespace App\Enums;

enum StaffDocument: string
{
    case Photo = 'photo';
    case IdentityCard = 'identity_card';
    case ApplicationForm = 'application_form';
    case ResidenceCertificate = 'residence_certificate';
    case HealthReport = 'health_report';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Fotoğraf',
            self::IdentityCard => 'Kimlik',
            self::ApplicationForm => 'İş başvuru formu',
            self::ResidenceCertificate => 'İkametgah',
            self::HealthReport => 'Sağlık raporu',
        };
    }
}
