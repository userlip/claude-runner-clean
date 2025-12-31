<?php

namespace App\Enums;

enum ProposalPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Low => '',
            self::Medium => '',
            self::High => '',
            self::Critical => '',
        };
    }
}
