<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case WaitingForInput = 'waiting_for_input';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::WaitingForInput => 'Waiting for Input',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Running => 'info',
            self::WaitingForInput => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
