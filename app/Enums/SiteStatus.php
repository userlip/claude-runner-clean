<?php

namespace App\Enums;

enum SiteStatus: string
{
    case Pending = 'pending';
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Provisioning => 'Provisioning',
            self::Active => 'Active',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Provisioning => 'warning',
            self::Active => 'success',
            self::Failed => 'danger',
        };
    }
}
