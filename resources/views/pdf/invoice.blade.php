<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Factuur {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            background: #fff;
        }

        .page { padding: 40px; }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 24px;
            border-bottom: 3px solid #f97316;
            margin-bottom: 32px;
        }

        .brand-name {
            font-size: 26px;
            font-weight: 700;
            color: #f97316;
            letter-spacing: -0.5px;
        }

        .brand-sub {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .doc-meta { text-align: right; }

        .doc-meta h1 {
            font-size: 22px;
            font-weight: 700;
            color: #111;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .doc-meta .number {
            font-size: 13px;
            color: #f97316;
            font-weight: 600;
            margin-top: 4px;
        }

        .doc-meta .date {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: #dcfce7;
            color: #16a34a;
        }

        /* Info grid */
        .info-grid {
            display: flex;
            gap: 20px;
            margin-bottom: 32px;
        }

        .info-block {
            flex: 1;
            background: #f9fafb;
            border-left: 3px solid #f97316;
            border-radius: 0 8px 8px 0;
            padding: 14px 16px;
        }

        .info-block-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #f97316;
            margin-bottom: 8px;
        }

        .info-block .name {
            font-size: 14px;
            font-weight: 700;
            color: #111;
            margin-bottom: 4px;
        }

        .info-block p {
            font-size: 11px;
            color: #4b5563;
            line-height: 1.6;
        }

        /* Tabel */
        .section-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        thead tr { background: #f97316; }

        thead th {
            padding: 10px 12px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        thead th.right { text-align: right; }

        tbody tr { border-bottom: 1px solid #f3f4f6; }
        tbody tr:nth-child(even) { background: #fafafa; }

        tbody td {
            padding: 10px 12px;
            font-size: 11px;
            color: #374151;
            vertical-align: top;
        }

        tbody td.right { text-align: right; }

        .missed {
            color: #dc2626;
            font-style: italic;
        }

        .deviation {
            font-size: 10px;
            color: #d97706;
            display: block;
            margin-top: 2px;
        }

        /* Totalen */
        .totals {
            width: 240px;
            margin-left: auto;
            margin-bottom: 32px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 12px;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
        }

        .totals-row.total {
            font-size: 15px;
            font-weight: 700;
            color: #111;
            border-bottom: none;
            border-top: 2px solid #f97316;
            padding-top: 10px;
            margin-top: 4px;
        }

        .totals-row span:last-child { font-weight: 600; }

        /* Betaalinfo */
        .payment-box {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 24px;
        }

        .payment-box-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #ea580c;
            margin-bottom: 8px;
        }

        .payment-box p {
            font-size: 11px;
            color: #4b5563;
            line-height: 1.7;
        }

        .payment-box .due {
            font-weight: 700;
            color: #ea580c;
        }

        /* Handtekening */
        .signature-section {
            display: flex;
            gap: 20px;
            margin-bottom: 24px;
        }

        .signature-block {
            flex: 1;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px;
        }

        .signature-block-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #9ca3af;
            margin-bottom: 10px;
        }

        .signature-block img {
            max-height: 70px;
            max-width: 100%;
        }

        .signature-block p {
            font-size: 11px;
            color: #374151;
            margin-top: 6px;
        }

        /* Footer */
        .footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
            line-height: 1.6;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        <div>
            <div class="brand-name">Koylu Food</div>
            <div class="brand-sub">Groothandel in vlees & levensmiddelen</div>
        </div>
        <div class="doc-meta">
            <h1>Factuur</h1>
            <div class="number">{{ $invoice->invoice_number }}</div>
            <div class="date">{{ $invoice->invoice_date?->format('d-m-Y') }}</div>
            <span class="status-badge">Geleverd</span>
        </div>
    </div>

    {{-- Info grid --}}
    <div class="info-grid">
        <div class="info-block">
            <div class="info-block-title">Factuuradres</div>
            <p class="name">{{ $invoice->order->customer->company_name }}</p>
            @if ($invoice->order->customer->contact_name)
                <p>t.a.v. {{ $invoice->order->customer->contact_name }}</p>
            @endif
            <p>{{ $invoice->order->customer->address }}</p>
            <p>{{ $invoice->order->customer->postal_code }} {{ $invoice->order->customer->city }}</p>
            <p>{{ $invoice->order->customer->country }}</p>
            @if ($invoice->order->customer->vat_number)
                <p>BTW: {{ $invoice->order->customer->vat_number }}</p>
            @endif
        </div>

        <div class="info-block">
            <div class="info-block-title">Factuurdetails</div>
            <p><strong>Factuurnummer:</strong> {{ $invoice->invoice_number }}</p>
            <p><strong>Ordernummer:</strong> {{ $invoice->order->order_number }}</p>
            <p><strong>Factuurdatum:</strong> {{ $invoice->invoice_date?->format('d-m-Y') }}</p>
            <p><strong>Vervaldatum:</strong> {{ $invoice->due_date?->format('d-m-Y') }}</p>
            <p><strong>Leverdatum:</strong> {{ $invoice->order->delivery?->delivered_at?->format('d-m-Y') ?? '-' }}</p>
        </div>
    </div>

    {{-- Artikelen --}}
    <div class="section-label">Artikelen</div>
    <table>
        <thead>
        <tr>
            <th style="width: 40%">Omschrijving</th>
            <th>Eenheid</th>
            <th class="right">Besteld</th>
            <th class="right">Geleverd</th>
            <th class="right">Stukprijs</th>
            <th class="right">Bedrag</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($invoice->order->items as $item)
            @php
                $deliveryItem = $invoice->order->delivery?->items
                    ->firstWhere('order_item_id', $item->id);
                $deliveredQty = $deliveryItem?->delivered_quantity ?? $item->quantity;
                $isMissed     = $deliveredQty == 0 && $deliveryItem?->missed_reason;
            @endphp
            <tr>
                <td>
                    {{ $item->product_name }}
                    @if ($isMissed)
                        <span class="missed">— niet geleverd</span>
                        <span class="deviation">{{ $deliveryItem->missed_reason }}</span>
                    @elseif ($deliveredQty != $item->quantity)
                        <span class="deviation">Afwijking: {{ $item->quantity - $deliveredQty }} {{ $item->unit }}</span>
                    @endif
                </td>
                <td>{{ $item->unit }}</td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="right">{{ $deliveredQty }}</td>
                <td class="right">€ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                <td class="right">€ {{ number_format($deliveredQty * $item->unit_price, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- Totalen --}}
    <div class="totals">
        <div class="totals-row">
            <span>Subtotaal</span>
            <span>€ {{ number_format($invoice->subtotal_amount, 2, ',', '.') }}</span>
        </div>
        @if ($invoice->order->customer->is_vat_exempt)
            <div class="totals-row">
                <span>BTW (0% — vrijgesteld)</span>
                <span>€ 0,00</span>
            </div>
        @else
            <div class="totals-row">
                <span>BTW (21%)</span>
                <span>€ {{ number_format($invoice->vat_amount, 2, ',', '.') }}</span>
            </div>
        @endif
        <div class="totals-row total">
            <span>Totaal</span>
            <span>€ {{ number_format($invoice->total_amount, 2, ',', '.') }}</span>
        </div>
    </div>

    {{-- Betaalinformatie --}}
    <div class="payment-box">
        <div class="payment-box-title">Betaalinformatie</div>
        <p>Gelieve het bedrag van <strong>€ {{ number_format($invoice->total_amount, 2, ',', '.') }}</strong> over te maken voor <span class="due">{{ $invoice->due_date?->format('d-m-Y') }}</span>.</p>
        <p>Onder vermelding van factuurnummer <strong>{{ $invoice->invoice_number }}</strong>.</p>
    </div>

    {{-- Handtekening --}}
    @if ($invoice->order->delivery?->signature_path)
        <div class="section-label">Ontvangstbevestiging</div>
        <div class="signature-section">
            <div class="signature-block">
                <div class="signature-block-title">Handtekening ontvanger</div>
                <img src="{{ storage_path('app/public/' . $invoice->order->delivery->signature_path) }}" alt="Handtekening" />
                <p>{{ $invoice->order->delivery->receiver_name ?? '-' }}</p>
            </div>
            <div class="signature-block">
                <div class="signature-block-title">Geleverd op</div>
                <p style="font-size: 15px; font-weight: 700; margin-top: 8px;">
                    {{ $invoice->order->delivery->delivered_at?->format('d-m-Y') }}
                </p>
                <p>{{ $invoice->order->delivery->delivered_at?->format('H:i') }} uur</p>
            </div>
        </div>
    @endif

    {{-- Notities --}}
    @if ($invoice->notes)
        <div class="payment-box" style="background: #f9fafb; border-color: #e5e7eb;">
            <div class="payment-box-title" style="color: #6b7280;">Opmerkingen</div>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <p>Koylu Food · KvK: XXXXXXXX · BTW: NL XXXXXXXXX B01</p>
        <p>{{ $invoice->invoice_number }} · Aangemaakt op {{ now()->format('d-m-Y H:i') }}</p>
    </div>

</div>
</body>
</html>
