@extends('documents.layout')
@php
    use App\Support\Decimal;
    $employee = $payment->employee;
@endphp

@section('title', "Bulletin {$periodLabel} — {$employee->name}")

@section('heading')
    <h1>BULLETIN DE PAIE</h1>
    <div class="strong">{{ ucfirst($periodLabel) }}</div>
    <div class="muted">Payé le {{ $payment->paid_at->format('d/m/Y') }}</div>
@endsection

@section('content')
    <div class="box">
        <div class="small muted">Employé</div>
        <div class="strong">{{ $employee->name }}</div>
        @if ($employee->position)<div>{{ $employee->position }}</div>@endif
    </div>

    <div class="spacer"></div>

    <table class="lines">
        <thead>
            <tr><th>Détail</th><th class="num">Montant</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    Salaire de base
                    @if ($payment->days_worked !== null)
                        <div class="small muted">{{ $payment->days_worked }} jour(s) × {{ Decimal::money($employee->salary_amount) }}</div>
                    @else
                        <div class="small muted">Salaire mensuel</div>
                    @endif
                </td>
                <td class="num">{{ Decimal::money($payment->base_amount) }}</td>
            </tr>
            @foreach ($advances as $advance)
                <tr>
                    <td>Avance du {{ $advance->spent_at->format('d/m/Y') }}</td>
                    <td class="num">− {{ Decimal::money($advance->amount) }}</td>
                </tr>
            @endforeach
            @if (Decimal::cmp($payment->carried_in, 0) > 0)
                <tr>
                    <td>Report : avances du mois précédent au-delà du salaire</td>
                    <td class="num">− {{ Decimal::money($payment->carried_in) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="spacer"></div>

    <table class="totals">
        <tr><td class="muted">Salaire de base</td><td class="num">{{ Decimal::money($payment->base_amount) }}</td></tr>
        <tr><td class="muted">Avances et report</td><td class="num">− {{ Decimal::money(Decimal::add($payment->advances_total, $payment->carried_in)) }}</td></tr>
        <tr class="total"><td>Net versé</td><td class="num">{{ Decimal::money($payment->net_paid) }}</td></tr>
        <tr><td class="muted">Mode</td><td class="num">{{ $payment->paymentMethod?->name ?? '—' }}</td></tr>
    </table>

    @php($excess = Decimal::sub(Decimal::add($payment->advances_total, $payment->carried_in), $payment->base_amount))
    @if (Decimal::cmp($excess, 0) > 0)
        <div class="spacer"></div>
        <div class="cancelled-note">Les avances dépassent le salaire de {{ Decimal::money($excess) }} : ce montant est reporté sur le mois suivant.</div>
    @endif

    <div class="footer">
        <table class="layout">
            <tr>
                <td class="center muted" style="width: 50%;">Signature de l’employé<div style="height: 60px;"></div></td>
                <td class="center muted" style="width: 50%;">Signature et cachet du garage<div style="height: 60px;"></div></td>
            </tr>
        </table>
    </div>
@endsection
