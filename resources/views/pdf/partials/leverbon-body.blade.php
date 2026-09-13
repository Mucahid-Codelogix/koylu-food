@php
    $customer = $order->customer;
    $route = $order->routeStops->first()?->route;
    $driver = $route?->driver;
@endphp

<div class="page"@if ($pageBreak ?? false) style="page-break-after: always;"@endif>

    {{-- Header --}}
    <table class="header">
        <tr>
        <td>
            <img src="{{ public_path(config('brand.logo')) }}" alt="{{ config('brand.name') }}" style="height: 48px; width: auto; margin-bottom: 4px;">
            <div class="company-sub">{{ config('brand.address') }}</div>
        </td>
        <td class="doc-title">
            <h1>Leverbon</h1>
            <div class="doc-number">{{ $order->delivery_note_number ?? '—' }}</div>
            <div class="doc-sub">Order {{ $order->order_number }}</div>
            <div style="margin-top: 6px;">
                <span class="status-badge {{ $isFinal ? 'status-badge-final' : 'status-badge-concept' }}">
                    {{ $isFinal ? 'Geleverd' : 'Te leveren' }}
                </span>
            </div>
        </td>
        </tr>
    </table>

    {{-- Info grid --}}
    <table class="layout-table">
        <tr>
        <td>
        <div class="info-block">
            <div class="info-block-title">Klant</div>
            <p class="name">{{ $customer->company_name }}</p>
            @if ($customer->contact_name)
                <p>{{ $customer->contact_name }}</p>
            @endif
            <p>{{ $customer->address }}</p>
            <p>{{ $customer->postal_code }} {{ $customer->city }}</p>
        </div>
        </td>
        <td>
        <div class="info-block">
            <div class="info-block-title">Leverdetails</div>
            @if ($isFinal)
                <p><strong>Leverdatum:</strong> {{ $delivery->delivered_at?->format('d-m-Y') ?? $delivery->created_at->format('d-m-Y') }}</p>
                <p><strong>Tijdstip:</strong> {{ $delivery->delivered_at?->format('H:i') ?? '-' }}</p>
                <p><strong>Ontvangen door:</strong> {{ $delivery->receiver_name ?: '-' }}</p>
            @else
                <p><strong>Leverdatum:</strong> {{ $route?->route_date?->format('d-m-Y') ?? $order->delivery_date?->format('d-m-Y') ?? '-' }}</p>
                <p><strong>Chauffeur:</strong> {{ $driver?->name ?? '-' }}</p>
                <p><strong>Orderdatum:</strong> {{ $order->order_date->format('d-m-Y') }}</p>
            @endif
        </div>
        </td>
        </tr>
    </table>

    {{-- Producten --}}
    <div class="section-title">{{ $isFinal ? 'Geleverde producten' : 'Te leveren producten' }}</div>
    <table>
        <thead>
        <tr>
            <th style="width: 36%">Product</th>
            <th>Eenheid</th>
            @if ($isFinal)
                <th class="num">Besteld</th>
                <th class="num">Geleverd</th>
            @else
                <th class="num">Aantal</th>
            @endif
            <th class="num">Gewicht</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($lines as $line)
            <tr>
                <td>
                    {{ $line['product_name'] }}
                    @if (filled($line['supplier_name']))
                        <br><span class="muted">{{ $line['supplier_name'] }}</span>
                    @endif
                </td>
                <td>{{ $line['unit'] }}</td>
                @if ($isFinal)
                    <td class="num">{{ $line['ordered_quantity'] }}</td>
                    <td class="num">
                        @if ($line['is_missed'])
                            <span class="missed">Niet geleverd</span>
                            <br><span class="deviation">{{ $line['missed_reason'] }}</span>
                        @else
                            {{ $line['delivered_quantity'] }}
                            @if ($line['has_deviation'])
                                <br><span class="deviation">Afwijking: {{ $line['deviation'] }}</span>
                                @if (filled($line['return_note']))
                                    <br><span class="deviation">{{ $line['return_note'] }}</span>
                                @endif
                            @endif
                        @endif
                    </td>
                @else
                    <td class="num">{{ $line['quantity'] }}</td>
                @endif
                <td class="num">{{ number_format($line['weight_kg'], 2, ',', '.') }} kg</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td colspan="{{ $isFinal ? 3 : 2 }}">Totaal</td>
            @if ($isFinal)
                <td class="num">{{ $totals['delivered_quantity'] }}</td>
            @else
                <td class="num">{{ $totals['quantity'] }}</td>
            @endif
            <td class="num">{{ number_format($totals['weight_kg'], 2, ',', '.') }} kg</td>
        </tr>
        </tfoot>
    </table>

    {{-- Kratten --}}
    <div class="keep-together">
    <div class="section-title">Kratten</div>
    <table class="layout-table">
        <tr>
        <td>
        <div class="crate-block">
            <div class="crate-label">Meegegeven kratten</div>
            @if ($isFinal)
                <div class="crate-value">{{ $crates['given'] }}</div>
            @else
                <div class="fill-box"></div>
            @endif
        </div>
        </td>
        <td>
        <div class="crate-block">
            <div class="crate-label">Retour ontvangen kratten</div>
            @if ($isFinal)
                <div class="crate-value">{{ $crates['returned'] }}</div>
            @else
                <div class="fill-box"></div>
            @endif
        </div>
        </td>
        </tr>
    </table>
    </div>

    {{-- Contante betaling: op de werkbon met pen in te vullen, op de definitieve bon alleen bij contant --}}
    @if (! $isFinal || ($cash['is_cash'] ?? false))
        <div class="keep-together">
        <div class="section-title">Contante betaling</div>
        <table class="layout-table">
            <tr>
            <td>
            <div class="crate-block">
                <div class="crate-label">Contant ontvangen bedrag</div>
                @if ($isFinal)
                    <div class="crate-value">€ {{ number_format($cash['amount'] ?? 0, 2, ',', '.') }}</div>
                @else
                    <div class="fill-box"></div>
                    <div class="sign-line">Alleen invullen bij contante betaling</div>
                @endif
            </div>
            </td>
            <td>
            <div class="signature-block">
                <div class="signature-block-title">Bezorger — geld ontvangen</div>
                @if ($isFinal)
                    @if ($cash['signature'] ?? null)
                        <img src="{{ $cash['signature'] }}" alt="Handtekening ontvangst geld" />
                    @else
                        <p style="margin-top: 26px;" class="muted">Geen handtekening vastgelegd</p>
                    @endif
                @else
                    <div class="sign-area"></div>
                @endif
            </div>
            </td>
            </tr>
        </table>
        </div>
    @endif

    {{-- Handtekeningen --}}
    <div class="keep-together">
    <div class="section-title">Ondertekening</div>
    <table class="layout-table">
        <tr>
        <td>
        <div class="signature-block">
            <div class="signature-block-title">Bezorger — geleverd</div>
            @if ($isFinal)
                @if ($signatures['driver'] ?? null)
                    <img src="{{ $signatures['driver'] }}" alt="Handtekening bezorger" />
                @else
                    <p style="margin-top: 26px;" class="muted">Geen handtekening vastgelegd</p>
                @endif
                <p><strong>{{ $delivery->driver_name ?: ($driver?->name ?? '-') }}</strong></p>
                <p class="muted">{{ $delivery->delivered_at?->format('d-m-Y H:i') ?? '-' }}</p>
            @else
                <div class="sign-area"></div>
                <div class="sign-line">Naam: <span></span></div>
            @endif
        </div>
        </td>
        <td>
        <div class="signature-block">
            <div class="signature-block-title">Klant — in ontvangst genomen</div>
            @if ($isFinal)
                @if ($signatures['receiver'] ?? null)
                    <img src="{{ $signatures['receiver'] }}" alt="Handtekening ontvanger" />
                @else
                    <p style="margin-top: 26px;" class="muted">Geen handtekening vastgelegd</p>
                @endif
                <p><strong>{{ $delivery->receiver_name ?: '-' }}</strong></p>
            @else
                <div class="sign-area"></div>
                <div class="sign-line">Naam: <span></span></div>
            @endif
        </div>
        </td>
        </tr>
    </table>
    </div>

    {{-- Pagina footer --}}
    <div class="page-footer">
        {{ config('brand.name') }} · Leverbon {{ $order->delivery_note_number ?? $order->order_number }} ·
        {{ $isFinal ? 'Definitieve versie' : 'Werkbon — aantallen en kratten met pen invullen' }} ·
        Afgedrukt op {{ now()->format('d-m-Y H:i') }}
    </div>

</div>
