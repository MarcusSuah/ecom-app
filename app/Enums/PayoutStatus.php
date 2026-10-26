<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
}
