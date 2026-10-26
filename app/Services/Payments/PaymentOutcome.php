<?php

namespace App\Services\Payments;

enum PaymentOutcome: string
{
    case SUCCEEDED = 'succeeded';
    case PENDING = 'pending';
    case FAILED = 'failed';
}
