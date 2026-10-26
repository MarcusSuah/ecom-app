<?php

namespace App\Actions\Products;

use DomainException;

class QuantityBelowMinimum extends DomainException
{
    public function __construct(public readonly int $moq, public readonly int $quantity)
    {
        parent::__construct("The minimum order quantity is {$moq}; {$quantity} was requested.");
    }
}
