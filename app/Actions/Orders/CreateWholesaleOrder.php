<?php

namespace App\Actions\Orders;

use App\Actions\Products\AlertLowStock;
use App\Actions\Products\PriceForQuantity;
use App\Actions\Products\QuantityBelowMinimum;
use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\QuoteStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Wholesale orders, awaiting payment, in the same orders table as local ones.
 */
class CreateWholesaleOrder
{
    public function __construct(private PriceForQuantity $priceForQuantity, private AlertLowStock $alertLowStock) {}

    /**
     * From an accepted quote: the requested quantity at the quote's tier
     * price. One order per quote.
     */
    public function fromQuote(Quote $quote, User $buyer): Order
    {
        $rfq = $quote->rfq;

        if ($quote->status !== QuoteStatus::ACCEPTED || $rfq->buyer_id !== $buyer->id) {
            throw ValidationException::withMessages(['quote' => 'Only the buyer can order from an accepted quote.']);
        }

        if ($quote->valid_until->endOfDay()->isPast()) {
            throw ValidationException::withMessages(['quote' => 'This quote has expired. Ask the supplier for a new one.']);
        }

        $tier = $quote->tierFor($rfq->quantity)
            ?? throw ValidationException::withMessages(['quote' => 'This quote has no price for the requested quantity.']);

        try {
            return $this->create(
                buyer: $buyer,
                sellerCompanyId: $quote->company_id,
                currency: $quote->currency,
                deliverTo: $rfq->deliver_city,
                purchasable: $rfq->product,
                name: $rfq->title,
                unitPrice: $tier->unit_price,
                quantity: $rfq->quantity,
                quoteId: $quote->id,
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['quote' => 'An order has already been placed for this quote.']);
        }
    }

    /**
     * Straight from a product page at its tier price (quantity at least the MOQ).
     */
    public function fromProduct(Product $product, User $buyer, int $quantity, string $deliverTo): Order
    {
        if (! $product->isActive() || ! $product->company->isListable()) {
            throw ValidationException::withMessages(['quantity' => 'This product is not available.']);
        }

        if ($product->company->owner_id === $buyer->id) {
            throw ValidationException::withMessages(['quantity' => 'You cannot order your own product.']);
        }

        try {
            $price = $this->priceForQuantity->handle($product->loadMissing('priceTiers'), $quantity);
        } catch (QuantityBelowMinimum $exception) {
            throw ValidationException::withMessages(['quantity' => 'The minimum order is '.number_format($exception->moq)." {$product->unit->value}."]);
        }

        return DB::transaction(function () use ($product, $buyer, $quantity, $deliverTo, $price): Order {
            // Listings with a stock count hold the units for this order; made-to-order ones have no limit.
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($locked->stock !== null) {
                if ($locked->stock < $quantity) {
                    throw ValidationException::withMessages(['quantity' => $locked->stock < $locked->moq
                        ? 'This product is out of stock.'
                        : 'Only '.number_format($locked->stock)." {$product->unit->value} are in stock."]);
                }

                $before = $locked->stockStatus();
                $locked->decrement('stock', $quantity);
                $this->alertLowStock->handle($locked, $before);
            }

            return $this->create(
                buyer: $buyer,
                sellerCompanyId: $product->company_id,
                currency: $price->currency,
                deliverTo: $deliverTo,
                purchasable: $product,
                name: $product->name,
                unitPrice: $price->unitPrice,
                quantity: $quantity,
            );
        });
    }

    private function create(User $buyer, int $sellerCompanyId, string $currency, string $deliverTo, ?Model $purchasable, string $name, int $unitPrice, int $quantity, ?int $quoteId = null): Order
    {
        return DB::transaction(function () use ($buyer, $sellerCompanyId, $currency, $deliverTo, $purchasable, $name, $unitPrice, $quantity, $quoteId): Order {
            $total = $unitPrice * $quantity;

            $order = new Order;
            $order->forceFill([
                'number' => 'ORD-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'buyer_id' => $buyer->id,
                'seller_company_id' => $sellerCompanyId,
                'quote_id' => $quoteId,
                'type' => OrderType::WHOLESALE,
                // Wholesale goods are shipped; freight is agreed in the quote.
                'fulfilment' => Fulfilment::DELIVERY,
                'address_snapshot' => ['label' => $deliverTo, 'lat' => null, 'lng' => null, 'description' => null, 'landmark' => null, 'distance_km' => null],
                'subtotal' => $total,
                'delivery_fee' => 0,
                'total' => $total,
                'currency' => $currency,
                'status' => OrderStatus::PENDING_PAYMENT,
                'placed_at' => now(),
            ])->save();

            $order->items()->make()->forceFill([
                'purchasable_type' => $purchasable?->getMorphClass(),
                'purchasable_id' => $purchasable?->getKey(),
                'name' => $name,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $total,
            ])->save();

            $order->history()->make()->forceFill([
                'from_status' => null,
                'to_status' => OrderStatus::PENDING_PAYMENT,
                'changed_by' => $buyer->id,
                'note' => $quoteId ? 'Ordered from an accepted quote' : 'Ordered from the product page',
                'created_at' => now(),
            ])->save();

            return $order;
        });
    }
}
