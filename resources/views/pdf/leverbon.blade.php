<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leverbon {{ $delivery->order->order_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 13px;
            color: #1a1a1a;
            background: #fff;
        }

        .page {
            padding: 40px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 2px solid #f97316;
        }

        .company-name {
            font-size: 22px;
            font-weight: 700;
            color: #f97316;
        }

        .company-sub {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h1 {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .doc-title .doc-number {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        /* Info blokken */
        .info-grid {
            display: flex;
            gap: 24px;
            margin-bottom: 32px;
        }

        .info-block {
            flex: 1;
            background: #f9fafb;
            border-radius: 8px;
            padding: 16px;
        }

        .info-block-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        .info-block p {
            font-size: 12px;
            color: #374151;
            line-height: 1.6;
        }

        .info-block .name {
            font-size: 14px;
            font-weight: 700;
            color: #1a1a1a;
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            background: #dcfce7;
            color: #16a34a;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 99px;
        }

        /* Tabel */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 32px;
        }

        thead tr {
            background: #f97316;
        }

        thead th {
            padding: 10px 14px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        thead th:last-child { text-align: right; }
        thead th:nth-child(3) { text-align: right; }
        thead th:nth-child(4) { text-align: right; }

        tbody tr {
            border-bottom: 1px solid #f3f4f6;
        }

        tbody tr:nth-child(even) {
            background: #fafafa;
        }

        tbody td {
            padding: 10px 14px;
            font-size: 12px;
            color: #374151;
        }

        tbody td:nth-child(3) { text-align: right; }
        tbody td:nth-child(4) { text-align: right; }
        tbody td:last-child  { text-align: right; }

        .missed {
            color: #dc2626;
            font-style: italic;
        }

        .deviation {
            font-size: 10px;
            color: #d97706;
        }

        /* Footer tabel */
        tfoot td {
            padding: 10px 14px;
            font-size: 12px;
            font-weight: 700;
            border-top: 2px solid #f97316;
        }

        /* Handtekening */
        .signature-section {
            display: flex;
            gap: 24px;
            margin-top: 16px;
        }

        .signature-block {
            flex: 1;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
        }

        .signature-block-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin-bottom: 10px;
        }

        .signature-block img {
            max-height: 80px;
            max-width: 100%;
        }

        .signature-block p {
            font-size: 12px;
            color: #374151;
            margin-top: 6px;
        }

        /* Footer pagina */
        .page-footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        <div>
            <div class="company-name">Koylu Food</div>
            <div class="company-sub">Leveringsbon</div>
        </div>
        <div class="doc-title">
            <h1>Leverbon</h1>
            <div class="doc-number">{{ $delivery->order->order_number }}</div>
            <div style="margin-top: 6px;">
                <span class="status-badge">Geleverd</span>
            </div>
        </div>
    </div>

    {{-- Info grid --}}
    <div class="info-grid">
        <div class="info-block">
            <div class="info-block-title">Klant</div>
            <p class="name">{{ $delivery->order->customer->company_name }}</p>
            @if ($delivery->order->customer->contact_name)
                <p>{{ $delivery->order->customer->contact_name }}</p>
            @endif
            <p>{{ $delivery->order->customer->address }}</p>
            <p>{{ $delivery->order->customer->postal_code }} {{ $delivery->order->customer->city }}</p>
        </div>

        <div class="info-block">
            <div class="info-block-title">Leverdetails</div>
            <p><strong>Leverdatum:</strong> {{ $delivery->delivered_at?->format('d-m-Y') ?? $delivery->created_at->format('d-m-Y') }}</p>
            <p><strong>Tijdstip:</strong> {{ $delivery->delivered_at?->format('H:i') ?? '-' }}</p>
            <p><strong>Ontvangen door:</strong> {{ $delivery->receiver_name ?? '-' }}</p>
            <p><strong>Orderdatum:</strong> {{ $delivery->order->order_date->format('d-m-Y') }}</p>
        </div>
    </div>

    {{-- Producten tabel --}}
    <div class="section-title">Geleverde producten</div>
    <table>
        <thead>
        <tr>
            <th style="width: 40%">Product</th>
            <th>Eenheid</th>
            <th>Besteld</th>
            <th>Geleverd</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($delivery->items as $item)
            <tr>
                <td>{{ $item->orderItem->product_name }}</td>
                <td>{{ $item->orderItem->unit }}</td>
                <td>{{ $item->ordered_quantity }}</td>
                <td>
                    @if ($item->delivered_quantity == 0 && $item->missed_reason)
                        <span class="missed">Niet geleverd</span>
                        <br>
                        <span class="deviation">{{ $item->missed_reason }}</span>
                    @else
                        {{ $item->delivered_quantity }}
                        @if ($item->delivered_quantity != $item->ordered_quantity)
                            <br>
                            <span class="deviation">
                                    Afwijking: {{ $item->ordered_quantity - $item->delivered_quantity }}
                                </span>
                        @endif
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td colspan="2">Totaal producten</td>
            <td>{{ $delivery->items->sum('ordered_quantity') }}</td>
            <td>{{ $delivery->items->sum('delivered_quantity') }}</td>
        </tr>
        </tfoot>
    </table>

    {{-- Handtekening --}}
    @if ($delivery->signature_path)
        <div class="section-title">Ontvangstbevestiging</div>
        <div class="signature-section">
            <div class="signature-block">
                <div class="signature-block-title">Handtekening ontvanger</div>
                <img src="{{ storage_path('app/public/' . $delivery->signature_path) }}" alt="Handtekening" />
                <p>{{ $delivery->receiver_name ?? '-' }}</p>
            </div>
            <div class="signature-block">
                <div class="signature-block-title">Geleverd op</div>
                <p style="font-size: 16px; font-weight: 700; margin-top: 8px;">
                    {{ $delivery->delivered_at?->format('d-m-Y') }}
                </p>
                <p>{{ $delivery->delivered_at?->format('H:i') }} uur</p>
            </div>
        </div>
    @endif

    {{-- Pagina footer --}}
    <div class="page-footer">
        Koylu Food · Leverbon {{ $delivery->order->order_number }} · Aangemaakt op {{ now()->format('d-m-Y H:i') }}
    </div>

</div>
</body>
</html>
