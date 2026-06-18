<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Factuur {{ $invoice->displayInvoiceNumber() }}</title>
    @php
        $brandRed = config('brand.colors.red');
        $brandBlack = config('brand.colors.black');
        $brandMuted = config('brand.colors.muted');
        $customer = $invoice->order->customer;
        $delivery = $invoice->order->delivery;
    @endphp
    <style>
        @page {
            margin: 0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: {{ $brandBlack }};
        }

        /* DomPDF negeert @page-marges soms — padding op wrapper is betrouwbaarder */
        .page {
            padding: 18mm 22mm 20mm 22mm;
        }

        table { border-collapse: collapse; width: 100%; }

        .text-muted { color: {{ $brandMuted }}; }
        .text-right { text-align: right; }
        .text-bold { font-weight: 700; }
        .nowrap { white-space: nowrap; }

        /* Header */
        .header-table td { vertical-align: top; padding: 0; }

        .brand-logo {
            height: 38px;
            width: auto;
            margin-bottom: 4px;
        }

        .company-line {
            font-size: 9px;
            color: {{ $brandMuted }};
            line-height: 1.45;
        }

        .doc-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: {{ $brandBlack }};
            margin-bottom: 2px;
        }

        .doc-number {
            font-size: 12px;
            font-weight: 700;
            color: {{ $brandRed }};
            margin-bottom: 6px;
        }

        .meta-table td {
            padding: 1px 0 1px 12px;
            font-size: 9px;
            vertical-align: top;
        }

        .meta-table td:first-child {
            padding-left: 0;
            color: {{ $brandMuted }};
            width: 88px;
        }

        .header-rule {
            border: none;
            border-top: 2px solid {{ $brandRed }};
            margin: 10px 0 12px;
        }

        /* Parties */
        .parties-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 10px 0 0;
        }

        .parties-table td:last-child { padding: 0 0 0 10px; }

        .block-label {
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: {{ $brandRed }};
            margin-bottom: 5px;
        }

        .party-name {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .party-lines p,
        .detail-lines p {
            font-size: 9px;
            color: #374151;
            line-height: 1.45;
        }

        .spacer-sm { height: 12px; }
        .spacer-md { height: 14px; }

        /* Line items */
        .items-table thead th {
            background: {{ $brandRed }};
            color: #fff;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 6px 8px;
            text-align: left;
        }

        .items-table thead th.right { text-align: right; }

        .items-table tbody td {
            padding: 6px 8px;
            font-size: 9px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .items-table tbody tr:nth-child(even) td { background: #fafafa; }

        .item-name { font-weight: 600; color: {{ $brandBlack }}; }

        .item-sub {
            display: block;
            font-size: 8px;
            color: {{ $brandMuted }};
            margin-top: 1px;
        }

        .item-note {
            display: block;
            font-size: 8px;
            color: #b45309;
            margin-top: 2px;
        }

        .item-missed {
            display: block;
            font-size: 8px;
            color: #dc2626;
            font-style: italic;
            margin-top: 2px;
        }

        /* Bottom: totals + payment */
        .bottom-table td { vertical-align: top; }

        .bottom-left { width: 55%; padding-right: 12px; }

        .bottom-right { width: 45%; }

        .totals-table td {
            padding: 3px 0;
            font-size: 9px;
        }

        .totals-table td:last-child {
            text-align: right;
            font-weight: 600;
            white-space: nowrap;
        }

        .totals-table tr.subtotal td { padding-top: 0; }

        .totals-table tr.grand td {
            border-top: 2px solid {{ $brandRed }};
            padding-top: 6px;
            font-size: 11px;
            font-weight: 700;
        }

        .totals-table tr.grand td:last-child { color: {{ $brandBlack }}; }

        .payment-block {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #d1d5db;
            font-size: 9px;
            color: #374151;
            line-height: 1.5;
        }

        .payment-block strong { color: {{ $brandBlack }}; }

        .notes-block {
            margin-top: 8px;
            padding: 6px 8px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            font-size: 8px;
            color: #4b5563;
        }

        .signature-box {
            margin-top: 8px;
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            font-size: 8px;
        }

        .signature-box img {
            max-height: 42px;
            max-width: 140px;
            margin-top: 4px;
        }

        .footer {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
            line-height: 1.4;
        }
    </style>
</head>
<body>
<div class="page">

{{-- Header --}}
<table class="header-table">
    <tr>
        <td style="width: 55%">
            <img src="{{ public_path(config('brand.logo')) }}" alt="{{ config('brand.name') }}" class="brand-logo">
            <div class="company-line">
                <strong>{{ config('brand.name') }}</strong><br>
                {{ config('brand.tagline') }}
            </div>
        </td>
        <td style="width: 45%" class="text-right">
            <div class="doc-title">Factuur</div>
            <div class="doc-number">{{ $invoice->displayInvoiceNumber() }}</div>
            <table class="meta-table" style="margin-left: auto;">
                <tr>
                    <td>Factuurdatum</td>
                    <td class="text-bold">{{ $invoice->invoice_date?->format('d-m-Y') }}</td>
                </tr>
                <tr>
                    <td>Vervaldatum</td>
                    <td class="text-bold">{{ $invoice->due_date?->format('d-m-Y') }}</td>
                </tr>
                <tr>
                    <td>Leverdatum</td>
                    <td>{{ $delivery?->delivered_at?->format('d-m-Y') ?? '—' }}</td>
                </tr>
                <tr>
                    <td>Order</td>
                    <td>{{ $invoice->order->order_number }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<hr class="header-rule">

{{-- Klant + details --}}
<table class="parties-table">
    <tr>
        <td>
            <div class="block-label">Factuuradres</div>
            <div class="party-name">{{ $customer->company_name }}</div>
            <div class="party-lines">
                @if ($customer->contact_name)
                    <p>t.a.v. {{ $customer->contact_name }}</p>
                @endif
                <p>{{ $customer->address }}</p>
                <p>{{ $customer->postal_code }} {{ $customer->city }}</p>
                @if ($customer->country)
                    <p>{{ $customer->country }}</p>
                @endif
                @if ($customer->vat_number)
                    <p>BTW {{ $customer->vat_number }}</p>
                @endif
            </div>
        </td>
        <td>
            <div class="block-label">Referenties</div>
            <div class="detail-lines">
                <p><span class="text-muted">Factuurnummer:</span> <strong>{{ $invoice->displayInvoiceNumber() }}</strong></p>
                <p><span class="text-muted">Ordernummer:</span> {{ $invoice->order->order_number }}</p>
                @if ($customer->email)
                    <p><span class="text-muted">E-mail:</span> {{ $customer->email }}</p>
                @endif
                @if ($delivery?->receiver_name)
                    <p><span class="text-muted">Ontvanger:</span> {{ $delivery->receiver_name }}</p>
                @endif
            </div>
        </td>
    </tr>
</table>

<div class="spacer-md"></div>

{{-- Regels --}}
<table class="items-table">
    <thead>
    <tr>
        <th style="width: 46%">Omschrijving</th>
        <th class="right" style="width: 18%">Geleverd (kg)</th>
        <th class="right" style="width: 18%">Prijs/kg</th>
        <th class="right" style="width: 18%">Bedrag</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($lines as $line)
        @php
            $orderedKg = round($line['ordered_quantity'] * $line['box_weight_kg'], 3);
        @endphp
        <tr>
            <td>
                <span class="item-name">{{ $line['product_name'] }}</span>
                <span class="item-sub">{{ $line['unit'] }}</span>
                @if ($line['is_missed'])
                    <span class="item-missed">Niet geleverd — {{ $line['missed_reason'] }}</span>
                @elseif ($line['delivered_kg'] != $orderedKg)
                    <span class="item-note">Afwijking: {{ rtrim(rtrim(number_format($orderedKg - $line['delivered_kg'], 3, ',', '.'), '0'), ',') }} kg t.o.v. bestelling</span>
                @endif
            </td>
            <td class="text-right">{{ rtrim(rtrim(number_format($line['delivered_kg'], 3, ',', '.'), '0'), ',') }}</td>
            <td class="text-right nowrap">€ {{ number_format($line['price_per_kg'], 4, ',', '.') }}</td>
            <td class="text-right nowrap">€ {{ number_format($line['line_subtotal'], 2, ',', '.') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="spacer-sm"></div>

{{-- Totalen + betaalinfo --}}
<table class="bottom-table">
    <tr>
        <td class="bottom-left">
            @if ($delivery?->signature_path)
                <div class="signature-box">
                    <strong>Ontvangstbevestiging</strong>
                    @if ($delivery->delivered_at)
                        <span class="text-muted"> · {{ $delivery->delivered_at->format('d-m-Y H:i') }}</span>
                    @endif
                    <br>
                    <img src="{{ storage_path('app/public/'.$delivery->signature_path) }}" alt="Handtekening">
                    @if ($delivery->receiver_name)
                        <br>{{ $delivery->receiver_name }}
                    @endif
                </div>
            @endif

            @if ($invoice->notes)
                <div class="notes-block">
                    <strong>Opmerkingen</strong><br>
                    {{ $invoice->notes }}
                </div>
            @endif
        </td>
        <td class="bottom-right">
            <table class="totals-table">
                <tr class="subtotal">
                    <td>Subtotaal excl. BTW</td>
                    <td>€ {{ number_format($invoice->subtotal_amount, 2, ',', '.') }}</td>
                </tr>
                @foreach ($vatByRate as $vatGroup)
                    <tr>
                        <td>
                            @if ($vatGroup['rate'] == 0)
                                BTW (0% — vrijgesteld)
                            @else
                                BTW ({{ number_format($vatGroup['rate'], 0, ',', '.') }}%)
                            @endif
                        </td>
                        <td>€ {{ number_format($vatGroup['vat_amount'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="grand">
                    <td>Totaal incl. BTW</td>
                    <td>€ {{ number_format($invoice->total_amount, 2, ',', '.') }}</td>
                </tr>
            </table>

            <div class="payment-block">
                Gelieve <strong>€ {{ number_format($invoice->total_amount, 2, ',', '.') }}</strong>
                te voldoen vóór <strong>{{ $invoice->due_date?->format('d-m-Y') }}</strong>,
                onder vermelding van factuurnummer <strong>{{ $invoice->displayInvoiceNumber() }}</strong>.
            </div>
        </td>
    </tr>
</table>

<div class="footer">
    {{ config('brand.name') }} · KvK XXXXXXXX · BTW NL XXXXXXXXX B01 · {{ $invoice->displayInvoiceNumber() }}
</div>

</div>
</body>
</html>
