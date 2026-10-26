<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case OPEN = 'open';
    case RESOLVED_RELEASE = 'resolved_release';
    case RESOLVED_REFUND = 'resolved_refund';
}
