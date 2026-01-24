<?php

namespace App\Enums;

enum MajorUpgradeStatus: string
{
    case Pending = 'pending';
    case Researching = 'researching';
    case Upgrading = 'upgrading';
    case Fixing = 'fixing';
    case Testing = 'testing';
    case PrOpened = 'pr_opened';
    case ReviewSiteCreated = 'review_site_created';
    case Completed = 'completed';
    case Failed = 'failed';
}
