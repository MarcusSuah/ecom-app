<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * Thrown by OrderStateMachine for a move the order's type does not allow.
 * A validation exception, so a web request gets the message back on the form.
 */
class InvalidOrderTransition extends ValidationException
{
    public static function for(Order $order, OrderStatus $to): static
    {
        return static::withMessages([
            'status' => "An order that is {$order->status->label()} cannot become {$to->label()}.",
        ]);
    }
}
