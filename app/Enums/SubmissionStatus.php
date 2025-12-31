<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Pending = 'pending';
    case Listed = 'listed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::NotSubmitted => 'Not Submitted',
            self::Pending => 'Pending Review',
            self::Listed => 'Listed',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotSubmitted => 'gray',
            self::Pending => 'warning',
            self::Listed => 'success',
            self::Rejected => 'danger',
        };
    }
}
