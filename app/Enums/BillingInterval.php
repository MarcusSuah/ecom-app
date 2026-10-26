<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum BillingInterval: string
{
    case MONTH = 'month';
    case YEAR = 'year';

    public function addTo(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::MONTH => $date->addMonthNoOverflow(),
            self::YEAR => $date->addYearNoOverflow(),
        };
    }
}
