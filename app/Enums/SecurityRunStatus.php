<?php

namespace App\Enums;

enum SecurityRunStatus: string
{
    case Pending = 'pending';
    case WaitingCi = 'waiting_ci';
    case Researching = 'researching';
    case Approved = 'approved';
    case Merged = 'merged';
    case Deployed = 'deployed';
    case Failed = 'failed';
}
