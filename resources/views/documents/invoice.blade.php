@extends('documents.layout')
@php
    use App\Support\Decimal;
    $ticket = $format === 'ticket';
    $vehicle = $order->vehicle;
@endphp

@section('title', "Facture {$invoice->number}")

@section('heading')
    <h1>FACTURE</h1>
    <div class="strong">{{ $invoice->number }}</div>
    <div class="muted">Du {{ $invoice->issued_at->format('d/m/Y') }}</div>
@endsection

@section('content')
    @if ($invoice->isCancelled())
        <div class="stamp">ANNULÉE</div>
    @endif

    @if ($ticket)
        <div><span class="strong">{{ $invoice->client->full_name }}</span> · {{ $invoice->client->phone }}</div>
        <div>{{ $vehicle->plate }} · {{ $vehicle->label() }}@if ($order->mileage) · {{ number_format($order->mileage, 0, ',', ' ') }} km @endif</div>
    @else
        <table class="layout stack">
            <tr>
                <td style="width: 50%; padding-right: 8px;">
                    <div class="box">
                        <div class="small muted">Client</div>
                        <div class="strong">{{ $invoice->client->full_name }}</div>
                        <div>{{ $invoice->client->phone }}</div>
                    </div>
                </td>
                <td style="width: 50%; padding-left: 8px;">
                    <div class="box">
                        <div class="small muted">Véhicule</div>
                        <div class="strong">{{ $vehicle->plate }}</div>
                        <div>{{ $vehicle->label() }}@if ($order->mileage) · {{ number_format($order->mileage, 0, ',', ' ') }} km @endif</div>
                    </div>
                </td>
            </tr>
        </table>
    @endif

    <div class="spacer"></div>

    <table class="lines">
        <thead>
            <tr>
                <th>Désignation</th>
                @unless ($ticket)
                    <th class="num">Qté</th>
                    <th class="num">Prix unitaire</th>
                @endunless
                <th class="num">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>
                        {{ $row['label'] }}
                        @if ($ticket && $row['quantity'] !== null && $row['unit_price'] !== null)
                            <div class="small muted">{{ $row['quantity'] }} × {{ Decimal::money($row['unit_price']) }}</div>
                        @endif
                    </td>
                    @unless ($ticket)
                        <td class="num">{{ $row['quantity'] ?? '' }}</td>
                        <td class="num">{{ $row['unit_price'] !== null ? Decimal::money($row['unit_price']) : '' }}</td>
                    @endunless
                    <td class="num">{{ $row['amount'] !== null ? Decimal::money($row['amount']) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="spacer"></div>

    <table class="totals">
        <tr><td class="muted">Main-d’œuvre</td><td class="num">{{ Decimal::money($invoice->labor_total) }}</td></tr>
        <tr><td class="muted">Pièces</td><td class="num">{{ Decimal::money($invoice->parts_total) }}</td></tr>
        @if (Decimal::cmp($invoice->discount, 0) > 0)
            <tr><td class="muted">Remise</td><td class="num">− {{ Decimal::money($invoice->discount) }}</td></tr>
        @endif
        <tr class="total"><td>Total</td><td class="num">{{ Decimal::money($invoice->total) }}</td></tr>
        @unless ($invoice->isCancelled())
            @if (Decimal::cmp($invoice->amount_paid, 0) > 0)
                <tr><td class="muted">Déjà payé</td><td class="num">{{ Decimal::money($invoice->amount_paid) }}</td></tr>
            @endif
            <tr><td class="strong">Reste à payer</td><td class="num strong">{{ Decimal::money($invoice->remaining()) }}</td></tr>
        @endunless
    </table>

    @if ($order->advice)
        <div class="spacer"></div>
        <div class="box"><span class="strong">Conseil :</span> {{ $order->advice }}</div>
    @endif

    @if ($invoice->isCancelled())
        <div class="spacer"></div>
        <div class="cancelled-note">Facture annulée le {{ $invoice->cancelled_at->format('d/m/Y') }} : {{ $invoice->cancel_reason }}</div>
    @endif

    <div class="footer center small muted">
        Prestation {{ $order->number }} · Merci de votre confiance.
    </div>
@endsection
