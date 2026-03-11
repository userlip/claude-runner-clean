<?php

namespace App\Enums;

enum PersonaStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Running = 'running';
    case AwaitingApproval = 'awaiting_approval';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Running => 'Running',
            self::AwaitingApproval => 'Awaiting Approval',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'gray',
            self::Running => 'warning',
            self::AwaitingApproval => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-play',
            self::Paused => 'heroicon-o-pause',
            self::Running => 'heroicon-o-arrow-path',
            self::AwaitingApproval => 'heroicon-o-clock',
        };
    }
}
