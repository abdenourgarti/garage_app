{{-- Mise en page commune des documents : écran (aperçu fluide), impression (A4 ou ticket 80 mm) et PDF (dompdf). --}}
@php($ticket = $format === 'ticket')
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        @page { size: {{ $ticket ? '80mm auto' : 'A4 portrait' }}; margin: {{ $ticket ? '3mm' : '14mm 14mm 16mm' }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #fff; }
        body { font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; font-size: {{ $ticket ? '11px' : '12px' }}; line-height: 1.4; color: #1d2a30; }
        .page { position: relative; max-width: {{ $ticket ? '80mm' : '794px' }}; margin: 0 auto; padding: {{ $format === 'pdf' ? '0' : ($ticket ? '3mm' : '28px') }}; }
        @media print { .page { max-width: none; padding: 0; } }
        h1 { margin: 0; font-size: {{ $ticket ? '14px' : '20px' }}; letter-spacing: .02em; }
        .muted { color: #5b6b73; }
        .small { font-size: {{ $ticket ? '9.5px' : '10.5px' }}; }
        .strong { font-weight: bold; }
        .center { text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .layout { width: 100%; border-collapse: collapse; }
        .layout td { vertical-align: top; padding: 0; }
        .garage-name { font-size: {{ $ticket ? '13px' : '16px' }}; font-weight: bold; }
        .logo { max-height: {{ $ticket ? '40px' : '64px' }}; max-width: 160px; }
        .box { border: 1px solid #d5dde1; border-radius: 6px; padding: 8px 10px; }
        .spacer { height: {{ $ticket ? '8px' : '18px' }}; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #5b6b73; border-bottom: 1.5px solid #1d2a30; padding: 5px 4px; }
        table.lines td { padding: 5px 4px; border-bottom: 1px solid #e3e8eb; vertical-align: top; }
        table.totals { width: {{ $ticket ? '100%' : '280px' }}; margin-left: auto; border-collapse: collapse; }
        table.totals td { padding: 3px 4px; }
        table.totals tr.total td { border-top: 1.5px solid #1d2a30; font-size: {{ $ticket ? '13px' : '15px' }}; font-weight: bold; padding-top: 6px; }
        .stamp { position: absolute; top: 120px; left: 0; right: 0; text-align: center; font-size: 64px; font-weight: bold; color: rgba(200, 40, 40, .18); transform: rotate(-18deg); letter-spacing: .1em; }
        .cancelled-note { border: 1px solid #c82828; color: #c82828; border-radius: 6px; padding: 6px 10px; }
        .footer { margin-top: {{ $ticket ? '10px' : '28px' }}; }
        /* Aperçu sur téléphone : l'en-tête et les deux encadrés passent l'un sous l'autre. */
        @media screen and (max-width: 560px) {
            .page { padding: 14px; }
            .layout.header > tbody > tr > td, .layout.header > tr > td, .layout.stack > tbody > tr > td, .layout.stack > tr > td { display: block; width: auto !important; padding: 0 0 8px !important; text-align: left; }
        }
    </style>
</head>
<body>
    <div class="page">
        @if ($ticket)
            <div class="center">
                @if ($garage['logo'])<img src="{{ $garage['logo'] }}" alt="" class="logo"><br>@endif
                <div class="garage-name">{{ $garage['name'] }}</div>
                @if ($garage['address'])<div class="small">{{ $garage['address'] }}</div>@endif
                @if ($garage['phone'])<div class="small">Tél. {{ $garage['phone'] }}</div>@endif
                <div class="spacer"></div>
                @yield('heading')
            </div>
        @else
            <table class="layout header">
                <tr>
                    <td>
                        <table class="layout">
                            <tr>
                                @if ($garage['logo'])<td style="width: 1%; padding-right: 12px;"><img src="{{ $garage['logo'] }}" alt="" class="logo"></td>@endif
                                <td>
                                    <div class="garage-name">{{ $garage['name'] }}</div>
                                    @if ($garage['address'])<div class="muted">{{ $garage['address'] }}</div>@endif
                                    @if ($garage['phone'])<div class="muted">Tél. {{ $garage['phone'] }}</div>@endif
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td class="num" style="width: 40%;">@yield('heading')</td>
                </tr>
            </table>
        @endif

        <div class="spacer"></div>
        @yield('content')
    </div>
    @if ($autoPrint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
