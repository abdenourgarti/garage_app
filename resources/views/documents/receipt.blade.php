@extends('documents.layout')
@php
    use App\Support\Decimal;
    $ticket = $format === 'ticket';
@endphp

@section('title', "Reçu de paiement {$invoice->number}")

@section('heading')
    <h1>REÇU DE PAIEMENT</h1>
    <div class="muted">Le {{ $payment->paid_at->format('d/m/Y à H:i') }}</div>
@endsection

@section('content')
    @if ($payment->cancelled_at)
        <div class="stamp">ANNULÉ</div>
    @endif

    <div class="{{ $ticket ? '' : 'box' }}">
        <div>Reçu de <span class="strong">{{ $payment->client->full_name }}</span> ({{ $payment->client->phone }})</div>
        <div class="spacer"></div>
        <div class="center" style="font-size: {{ $ticket ? '18px' : '26px' }}; font-weight: bold;">{{ Decimal::money($payment->amount) }}</div>
        <div class="center muted">Payé par {{ mb_strtolower($payment->paymentMethod->name) }}</div>
    </div>

    <div class="spacer"></div>

    <table class="totals" style="width: 100%;">
        <tr><td class="muted">Facture</td><td class="num">{{ $invoice->number }} du {{ $invoice->issued_at->format('d/m/Y') }}</td></tr>
        <tr><td class="muted">Véhicule</td><td class="num">{{ $invoice->serviceOrder->vehicle->plate }}</td></tr>
        <tr><td class="muted">Total de la facture</td><td class="num">{{ Decimal::money($invoice->total) }}</td></tr>
        <tr class="total"><td>Reste à payer</td><td class="num">{{ Decimal::money($payment->cancelled_at ? $invoice->remaining() : $remainingAfter) }}</td></tr>
    </table>

    @if ($payment->cancelled_at)
        <div class="spacer"></div>
        <div class="cancelled-note">Paiement annulé le {{ $payment->cancelled_at->format('d/m/Y') }} avec la facture.</div>
    @endif

    @unless ($ticket)
        <div class="footer">
            <table class="layout"><tr><td></td><td style="width: 40%;" class="center muted">Signature et cachet<div style="height: 60px;"></div></td></tr></table>
        </div>
    @endunless
    <div class="footer center small muted">Merci de votre confiance.</div>
@endsection
