<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Factuur {{ $invoice->displayInvoiceNumber() }}</title>
    @php
        $brandRed   = config('brand.colors.red');
        $brandBlack = config('brand.colors.black');
        $brandMuted = config('brand.colors.muted');
        $customer   = $invoice->order->customer;
        $delivery   = $invoice->order->delivery;
    @endphp
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.5;
            color: {{ $brandBlack }};
        }

        .page {
            padding: 16mm 20mm 18mm 20mm;
        }

        table { border-collapse: collapse; width: 100%; }

        /* ── Header ── */
        .header-table td { vertical-align: top; padding: 0; }

        .brand-logo {
            height: 52px;
            width: auto;
            display: block;
        }

        .company-info {
            margin-top: 6px;
            font-size: 8.5px;
            color: #374151;
            line-height: 1.55;
        }

        .doc-title {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: {{ $brandRed }};
            text-align: right;
            line-height: 1;
            margin-bottom: 10px;
        }

        .customer-block {
            text-align: left;
            font-size: 10px;
            line-height: 1.55;
        }

        .customer-block .customer-name {
            font-weight: 700;
            font-size: 11px;
        }

        /* ── Meta row ── */
        .meta-row {
            width: 100%;
            border-top: 1.5px solid {{ $brandBlack }};
            border-bottom: 1.5px solid {{ $brandBlack }};
            margin: 12px 0 14px;
        }

        .meta-row td {
            padding: 5px 10px 5px 0;
            font-size: 9px;
            vertical-align: top;
        }

        .meta-row td:last-child { padding-right: 0; }

        .meta-label {
            font-weight: 700;
            display: block;
            margin-bottom: 1px;
        }

        .meta-value {
            display: block;
            color: #374151;
        }

        /* ── Line items ── */
        .items-table thead th {
            font-size: 9px;
            font-weight: 700;
            text-align: left;
            padding: 5px 8px 5px 0;
            border-bottom: 1.5px solid {{ $brandBlack }};
            border-top: 1.5px solid {{ $brandBlack }};
        }

        .items-table thead th.right { text-align: right; }

        .items-table tbody td {
            padding: 5px 8px 5px 0;
            font-size: 9px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .items-table tbody td:last-child { padding-right: 0; }
        .items-table thead th:last-child { padding-right: 0; }

        .text-right { text-align: right; }
        .nowrap { white-space: nowrap; }

        .item-note {
            display: block;
            font-size: 8px;
            color: #b45309;
            margin-top: 1px;
        }

        .item-missed {
            display: block;
            font-size: 8px;
            color: #dc2626;
            font-style: italic;
            margin-top: 1px;
        }

        /* ── Totals ── */
        .totals-wrap {
            margin-top: 10px;
            text-align: right;
        }

        .totals-table {
            width: auto;
            margin-left: auto;
            min-width: 220px;
        }

        .totals-table td {
            padding: 3px 0 3px 20px;
            font-size: 9.5px;
        }

        .totals-table td:first-child { padding-left: 0; text-align: left; }
        .totals-table td:last-child { text-align: right; white-space: nowrap; }

        .totals-table .total-border td {
            border-top: 1px solid #d1d5db;
        }

        .totals-table .grand td {
            border-top: 1.5px solid {{ $brandBlack }};
            border-bottom: 1.5px solid {{ $brandBlack }};
            font-weight: 700;
            font-size: 11px;
            padding-top: 5px;
            padding-bottom: 5px;
        }

        /* ── Footer area ── */
        .footer-rule {
            border: none;
            border-top: 1px solid #d1d5db;
            margin: 18px 0 12px;
        }

        .voldaan-line {
            font-size: 10px;
            font-style: italic;
            margin-bottom: 18px;
        }

        .payment-notice {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.01em;
            margin-bottom: 12px;
        }

        .bank-info {
            font-size: 8.5px;
            color: #374151;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .legal-note {
            font-size: 8px;
            color: {{ $brandMuted }};
            line-height: 1.4;
        }
    </style>
</head>
<body>
<div class="page">

{{-- ── Header ── --}}
<table class="header-table">
    <tr>
        <td style="width: 55%">
            <img src="{{ public_path(config('brand.logo')) }}" alt="{{ config('brand.name') }}" class="brand-logo">
            <div class="company-info">
                www.koylufood.com - info@koylufood.com<br>
                {{ config('brand.address', 'Grobbendonkstraat 3 (5628 RE) te Eindhoven') }}<br>
                Tel./Fax: {{ config('brand.phone', '0031 40 248 1979') }}
            </div>
        </td>
        <td style="width: 45%">
            <div class="doc-title">FACTUUR</div>
            <div class="customer-block">
                <span class="customer-name">{{ $customer->company_name }}</span><br>
                {{ $customer->address }}<br>
                {{ $customer->postal_code }} {{ $customer->city }}
                @if ($customer->country && strtolower($customer->country) !== 'nederland')
                    <br>{{ $customer->country }}
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ── Meta row ── --}}
<table class="meta-row">
    <tr>
        <td>
            <span class="meta-label">Uw BTW-nummer</span>
            <span class="meta-value">{{ $customer->vat_number ?: '—' }}</span>
        </td>
        <td>
            <span class="meta-label">Debiteurnummer</span>
            <span class="meta-value">{{ $customer->id }}</span>
        </td>
        <td>
            <span class="meta-label">Factuurdatum</span>
            <span class="meta-value">{{ $invoice->invoice_date?->translatedFormat('j F Y') ?? '—' }}</span>
        </td>
        <td>
            <span class="meta-label">Vervaldatum</span>
            <span class="meta-value">{{ $invoice->due_date?->translatedFormat('j F Y') ?? '—' }}</span>
        </td>
        <td>
            <span class="meta-label">Factuurnummer</span>
            <span class="meta-value">{{ $invoice->displayInvoiceNumber() }}</span>
        </td>
    </tr>
</table>

{{-- ── Line items ── --}}
<table class="items-table">
    <thead>
    <tr>
        <th style="width: 10%">Artikelcode</th>
        <th style="width: 42%">Artikelomschrijving</th>
        <th class="right" style="width: 16%">Aantal in kg</th>
        <th class="right" style="width: 16%">Prijs per kg</th>
        <th class="right" style="width: 16%">Totaalbedrag</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($lines as $line)
        @php
            $orderedKg = round($line['ordered_quantity'] * $line['box_weight_kg'], 3);
        @endphp
        <tr>
            <td>{{ $line['article_code'] ?: '—' }}</td>
            <td>
                {{ $line['product_name'] }}
                @if ($line['is_missed'])
                    <span class="item-missed">Niet geleverd — {{ $line['missed_reason'] }}</span>
                @elseif ($line['delivered_kg'] != $orderedKg)
                    <span class="item-note">Afwijking: {{ rtrim(rtrim(number_format($orderedKg - $line['delivered_kg'], 3, ',', '.'), '0'), ',') }} kg t.o.v. bestelling</span>
                @endif
            </td>
            <td class="text-right">{{ number_format($line['delivered_kg'], 2, ',', '.') }}</td>
            <td class="text-right nowrap">€ {{ number_format($line['price_per_kg'], 2, ',', '.') }}</td>
            <td class="text-right nowrap">€ {{ number_format($line['line_subtotal'], 2, ',', '.') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

{{-- ── Totals ── --}}
<div class="totals-wrap">
    <table class="totals-table">
        <tr class="total-border">
            <td>Totaal exclusief BTW</td>
            <td>€ {{ number_format($invoice->subtotal_amount, 2, ',', '.') }}</td>
        </tr>
        @foreach ($vatByRate as $vatGroup)
            <tr>
                <td>
                    BTW-totaal
                    @if ($vatGroup['rate'] > 0)
                        ({{ number_format($vatGroup['rate'], 0) }}%)
                    @endif
                </td>
                <td>€ {{ number_format($vatGroup['vat_amount'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="grand">
            <td>Totaal te voldoen</td>
            <td>€ {{ number_format($invoice->total_amount, 2, ',', '.') }}</td>
        </tr>
    </table>
</div>

{{-- ── Footer ── --}}
<hr class="footer-rule">

<div class="voldaan-line">
    <em>Voldaan op: ......./.......{{ now()->year }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Paraaf: .......................</em>
</div>

<div class="payment-notice">
    GELIEVE BINNEN 7 DAGEN NA FACTUURDATUM TE BETALEN
</div>

<div class="bank-info">
    IBAN: {{ config('brand.iban', 'NL 41 ABNA 0608 3930 37') }} - BIC: {{ config('brand.bic', 'ABNANL2A') }}<br>
    BTW nr.: {{ config('brand.vat_number', 'NL 808966054B01') }} - KvK nr.: {{ config('brand.kvk', '17090406') }}
</div>

<div class="legal-note">
    Prijsopgaven, transacties en leveringen geschieden volgens de leveringsvoorwaarden, gedeponeerd bij de KvK te Eindhoven.
</div>

</div>
</body>
</html>
