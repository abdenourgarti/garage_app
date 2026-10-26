@extends('documents.layout')
@php
    use App\Support\Decimal;
    $km = fn (?int $value) => $value === null ? '—' : number_format($value, 0, ',', "\u{00A0}")."\u{00A0}km";
    $statuses = ['ok' => 'OK', 'soon' => 'Bientôt', 'overdue' => 'Dépassée'];
@endphp

@section('title', "Bilan d'entretien — {$vehicle->plate}")

@section('heading')
    <h1>BILAN D'ENTRETIEN</h1>
    <div class="strong">{{ $vehicle->plate }}</div>
    <div class="muted">Édité le {{ now()->format('d/m/Y') }}</div>
@endsection

@section('content')
    <table class="layout stack">
        <tr>
            <td style="width: 50%; padding-right: 8px;">
                <div class="box">
                    <div class="small muted">Véhicule</div>
                    <div class="strong">{{ $vehicle->plate }} — {{ $vehicle->label() }}@if ($vehicle->year) ({{ $vehicle->year }})@endif</div>
                    @if ($vehicle->energy)<div>{{ $vehicle->energy->name }}</div>@endif
                    @if ($vehicle->vin)<div class="small">Châssis : {{ $vehicle->vin }}</div>@endif
                </div>
            </td>
            <td style="width: 50%; padding-left: 8px;">
                <div class="box">
                    <div class="small muted">Kilométrage</div>
                    <div class="strong">{{ $km($reminder['estimated_km'] ?? null) }} estimés aujourd'hui</div>
                    <div class="small muted">{{ count($orders) }} prestation(s) au garage</div>
                    <div class="small">Propriétaire : {{ $vehicle->client->full_name }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="spacer"></div>

    @forelse ($orders as $order)
        <table class="lines" style="page-break-inside: avoid; margin-bottom: 12px;">
            <thead>
                <tr>
                    <th>{{ $order['date'] }} · {{ $km($order['mileage']) }} <span class="muted">· {{ $order['number'] }}</span></th>
                    @if ($withPrices)<th class="num">Montant</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($order['lines'] as $line)
                    <tr>
                        <td class="strong">{{ $line['label'] }}</td>
                        @if ($withPrices)<td class="num">{{ Decimal::money($line['price']) }}</td>@endif
                    </tr>
                    @foreach ($line['parts'] as $part)
                        <tr>
                            <td style="padding-left: 16px;">{{ $part['label'] }} <span class="muted">{{ $part['quantity'] }}</span></td>
                            @if ($withPrices)<td class="num">{{ $part['price'] === null ? '—' : Decimal::money($part['price']) }}</td>@endif
                        </tr>
                    @endforeach
                @endforeach
                @foreach ($order['parts'] as $part)
                    <tr>
                        <td>{{ $part['label'] }} <span class="muted">{{ $part['quantity'] }}</span></td>
                        @if ($withPrices)<td class="num">{{ $part['price'] === null ? '—' : Decimal::money($part['price']) }}</td>@endif
                    </tr>
                @endforeach
                @if ($order['advice'])
                    <tr><td colspan="2" class="small"><span class="strong">Conseil :</span> {{ $order['advice'] }}</td></tr>
                @endif
                @if ($withPrices)
                    <tr><td class="num strong">Total</td><td class="num strong">{{ Decimal::money($order['total']) }}</td></tr>
                @endif
            </tbody>
        </table>
    @empty
        <p class="muted">Aucune prestation terminée pour ce véhicule.</p>
    @endforelse

    @if (! empty($reminder['due']))
        <div class="spacer"></div>
        <div class="box">
            <div class="strong">Prochains entretiens conseillés</div>
            <table class="lines">
                @foreach ($reminder['due'] as $due)
                    <tr>
                        <td>{{ $due['type'] }}</td>
                        <td>à {{ $km($due['due_km']) }}</td>
                        <td class="small muted">fait le {{ \Carbon\Carbon::parse($due['done_at'])->format('d/m/Y') }} à {{ $km($due['done_km']) }}</td>
                        <td class="num">{{ $statuses[$due['status']] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
@endsection
