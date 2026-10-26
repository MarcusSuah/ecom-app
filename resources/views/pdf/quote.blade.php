@extends('pdf.layout')

@section('content')
    <table class="grid">
        <tr>
            <td>
                <h2>From</h2>
                @if ($supplierLogo)
                    <img src="{{ $supplierLogo }}" alt="" style="height: 34px; margin-bottom: 4px;"><br>
                @endif
                <strong>{{ $quote->company->name }}</strong>
                @if ($quote->company->city)<br>{{ $quote->company->city }}@endif
                @if ($quote->company->phone)<br>{{ $quote->company->phone }}@endif
                @if ($quote->company->email)<br>{{ $quote->company->email }}@endif
            </td>
            <td>
                <h2>For</h2>
                <strong>{{ $buyerName }}</strong>
                @if ($buyerContact)<br>{{ $buyerContact }}@endif
                <br>Deliver to {{ $quote->rfq->deliver_city }}
            </td>
        </tr>
    </table>

    <h2>Request</h2>
    <strong>{{ $quote->rfq->title }}</strong>
    <div class="muted">{{ number_format($quote->rfq->quantity) }} {{ $quote->rfq->unit->value }} requested · {{ $quote->rfq->category->name }}</div>

    <table class="lines">
        <thead>
            <tr>
                <th>Quantity</th>
                <th class="num">Price per {{ $quote->rfq->unit->value }}</th>
                <th class="num">For the requested quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tiers as $index => $tier)
                <tr>
                    <td>
                        {{ number_format($tier->min_qty) }}{{ isset($tiers[$index + 1]) ? ' to '.number_format($tiers[$index + 1]->min_qty - 1) : '+' }}
                    </td>
                    <td class="num">{{ $money($tier->unit_price) }}</td>
                    <td class="num">
                        @if ($quote->rfq->quantity >= $tier->min_qty && (! isset($tiers[$index + 1]) || $quote->rfq->quantity < $tiers[$index + 1]->min_qty))
                            <strong>{{ $money($tier->unit_price * $quote->rfq->quantity) }}</strong>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Lead time</td><td class="num">{{ $quote->leadTimeLabel() }}</td></tr>
        <tr><td>Valid until</td><td class="num">{{ $quote->valid_until->format('j M Y') }}</td></tr>
        <tr><td>Status</td><td class="num">{{ ucfirst($quote->status->value) }}</td></tr>
        @if ($estimate !== null)
            <tr class="grand"><td>Estimated total</td><td class="num">{{ $money($estimate) }}</td></tr>
        @endif
    </table>

    @if ($quote->sample_terms)
        <h2>Samples</h2>
        <p>{{ $quote->sample_terms }}</p>
    @endif

    @if ($quote->message)
        <h2>Message from the supplier</h2>
        <p>{{ $quote->message }}</p>
    @endif

    <div class="note">
        Freight, duties and the final price are confirmed when you accept the quote on {{ $tenant->name }}.
        Payment is protected: the supplier is paid only after you confirm delivery.
    </div>
@endsection
