<?php

namespace App\Enums;

enum SecurityRunStatus: string
{
    case Pending = 'pending';
    case WaitingCi = 'waiting_ci';
    case FixingCi = 'fixing_ci';
    case Researching = 'researching';
    case NeedsUserAction = 'needs_user_action';
    case Approved = 'approved';
    case Merged = 'merged';
    case Deployed = 'deployed';
    case Closed = 'closed';  // User intentionally closed/ignored the PR
    case Failed = 'failed';
}
