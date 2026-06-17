<?php

namespace App\Enums;

enum SiteStatus: string
{
    case New = 'new';
    case Screening = 'screening';
    case Inspection = 'inspection';
    case Scoring = 'scoring';
    case Negotiation = 'negotiation';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Launched = 'launched';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новая',
            self::Screening => 'Скрининг',
            self::Inspection => 'Осмотр',
            self::Scoring => 'Скоринг',
            self::Negotiation => 'Согласование',
            self::Approved => 'Согласована',
            self::Rejected => 'Отклонена',
            self::Launched => 'Запущена',
            self::Archived => 'В архиве',
        };
    }

    public static function labelFor(string $value): string
    {
        return (self::tryFrom($value)?->label()) ?? $value;
    }
}
