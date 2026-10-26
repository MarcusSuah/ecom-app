@extends('pdf.layout')

@section('content')
    <table class="grid">
        <tr>
            <td>
                <h2>Billed to</h2>
                <strong>{{ $subscriberName }}</strong>
                @if ($subscriberEmail)<br>{{ $subscriberEmail }}@endif
            </td>
            <td>
                <h2>Status</h2>
                <span class="pill {{ $invoice->status->value === 'paid' ? 'good' : 'warn' }}">{{ ucfirst($invoice->status->value) }}</span>
                @if ($invoice->paid_at)<div class="muted">Paid {{ $invoice->paid_at->format('j M Y') }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Plan</th>
                <th>Period</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->subscription->plan->name }} ({{ $invoice->subscription->interval->value === 'year' ? 'yearly' : 'monthly' }})</td>
                <td>{{ $invoice->period_starts_at->format('j M Y') }} – {{ $invoice->period_ends_at->format('j M Y') }}</td>
                <td class="num">{{ $money($invoice->amount) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand"><td>{{ $invoice->status->value === 'paid' ? 'Paid' : 'Total due' }}</td><td class="num">{{ $money($invoice->amount) }}</td></tr>
    </table>

    @if ($invoice->provider_reference)
        <div class="note">Payment reference {{ $invoice->provider_reference }}.</div>
    @endif
@endsection
