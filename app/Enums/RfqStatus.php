<?php

namespace App\Enums;

enum RfqStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case AWARDED = 'awarded';
    case EXPIRED = 'expired';
}
