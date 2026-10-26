@extends('pdf.layout')

@section('content')
    <table class="grid">
        <tr>
            <td>
                <h2>Billed to</h2>
                <strong>{{ $order->buyer->name }}</strong><br>
                {{ $order->buyer->email }}
                @if ($order->buyer->phone)<br>{{ $order->buyer->phone }}@endif
                @if ($order->address_snapshot)
                    <br><span class="muted">Deliver to:</span> {{ $order->address_snapshot['label'] }}
                    @if (! empty($order->address_snapshot['landmark'])) ({{ $order->address_snapshot['landmark'] }})@endif
                @endif
            </td>
            <td>
                <h2>Sold by</h2>
                <strong>{{ $order->seller->name }}</strong><br>
                @if ($order->seller->city){{ $order->seller->city }}, @endif{{ $order->seller->country }}
                @if ($order->seller->email)<br>{{ $order->seller->email }}@endif
                @if ($order->seller->phone)<br>{{ $order->seller->phone }}@endif
            </td>
        </tr>
    </table>

    <h2>Order</h2>
    <div>
        <span class="pill">{{ ucfirst($order->type->value) }} · {{ ucfirst($order->fulfilment->value) }}</span>
        <span class="pill {{ $paid ? 'good' : 'warn' }}">{{ $order->status->label() }}</span>
        @if ($order->placed_at)<span class="muted"> · placed {{ $order->placed_at->format('j M Y, H:i') }}</span>@endif
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Quantity</th>
                <th class="num">Unit price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td class="num">{{ number_format($item->quantity) }}</td>
                    <td class="num">{{ $money($item->unit_price) }}</td>
                    <td class="num">{{ $money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $money($order->subtotal) }}</td></tr>
        @if ($order->delivery_fee > 0 || $order->fulfilment->value === 'delivery')
            <tr><td>Delivery</td><td class="num">{{ $order->delivery_fee > 0 ? $money($order->delivery_fee) : 'Free' }}</td></tr>
        @endif
        <tr class="grand"><td>{{ $paid ? 'Paid' : 'Total due' }}</td><td class="num">{{ $money($order->total) }}</td></tr>
    </table>

    @if ($payment)
        <div class="note">
            Paid with {{ $paymentProvider }} on {{ ($payment->held_at ?? $payment->created_at)?->format('j M Y') }} · reference {{ $payment->reference }}.
            The payment is held under {{ $protectionLabel }} until the order is received.
        </div>
    @else
        <div class="note" style="border-left-color: #f2b632;">
            Not paid yet. Pay from your order page on {{ $tenant->name }}; payments are held under {{ $protectionLabel }} until you confirm delivery.
        </div>
    @endif
@endsection
