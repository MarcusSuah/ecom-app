@extends('pdf.layout')

@section('content')
    <h2>Seller</h2>
    <strong>{{ $company->name }}</strong>
    @if ($company->email)<br>{{ $company->email }}@endif
    <div class="muted">Payouts recorded from {{ $period->from->format('j M Y') }} to {{ $period->to->format('j M Y') }}</div>

    <table class="lines">
        <thead>
            <tr>
                <th>Order</th>
                <th>Recorded</th>
                <th class="num">Gross</th>
                <th class="num">Commission</th>
                <th class="num">Net</th>
                <th>Paid</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payouts as $payout)
                <tr>
                    <td>{{ $payout->order->number }}</td>
                    <td>{{ $payout->created_at?->format('j M Y') }}</td>
                    <td class="num">{{ \App\Support\Money::format($payout->gross, $payout->currency) }}</td>
                    <td class="num">{{ \App\Support\Money::format($payout->commission, $payout->currency) }} ({{ (float) $payout->commission_rate }}%)</td>
                    <td class="num">{{ \App\Support\Money::format($payout->net, $payout->currency) }}</td>
                    <td>{{ $payout->paid_at ? $payout->paid_at->format('j M Y').' · '.$payout->reference : 'Pending' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No payouts in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    @foreach ($totals as $total)
        <table class="totals">
            <tr><td>Gross ({{ $total['currency'] }})</td><td class="num">{{ $total['gross'] }}</td></tr>
            <tr><td>Commission</td><td class="num">{{ $total['commission'] }}</td></tr>
            <tr><td>Paid out</td><td class="num">{{ $total['paid'] }}</td></tr>
            <tr><td>Still to pay</td><td class="num">{{ $total['pending'] }}</td></tr>
            <tr class="grand"><td>Net</td><td class="num">{{ $total['net'] }}</td></tr>
        </table>
    @endforeach
@endsection
