<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case SUBMITTED = 'submitted';
    case WITHDRAWN = 'withdrawn';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
}
