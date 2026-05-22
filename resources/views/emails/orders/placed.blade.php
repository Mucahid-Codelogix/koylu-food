<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bestelbevestiging</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: {{ config('brand.colors.surface') }};">

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: {{ config('brand.colors.surface') }}; padding: 32px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 640px; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding: 32px 32px 0;">
                        <x-brand.email-header>
                            Bestelbevestiging
                        </x-brand.email-header>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 0 32px 8px; font-family: Arial, sans-serif;">
                        <h1 style="margin: 0 0 8px; font-size: 22px; color: {{ config('brand.colors.black') }};">Bedankt voor uw bestelling</h1>
                        <p style="margin: 0; font-size: 14px; line-height: 1.6; color: {{ config('brand.colors.muted') }};">
                            Uw bestelling is succesvol ontvangen en wordt zo spoedig mogelijk verwerkt.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 20px 32px;">
                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background: {{ config('brand.colors.surface') }}; border-radius: 8px; border-left: 3px solid {{ config('brand.colors.red') }};">
                            <tr>
                                <td style="padding: 16px 20px; font-size: 14px; color: {{ config('brand.colors.black') }};">
                                    <p style="margin: 0 0 6px;"><strong>Ordernummer:</strong> {{ $order->order_number }}</p>
                                    <p style="margin: 0;"><strong>Datum:</strong> {{ $order->order_date->format('d-m-Y H:i') }}</p>
                                    @if ($order->delivery_date)
                                        <p style="margin: 6px 0 0;"><strong>Gewenste levering:</strong> {{ $order->delivery_date->format('d-m-Y') }}</p>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 0 32px 24px;">
                        <table width="100%" cellpadding="10" cellspacing="0" role="presentation" style="border-collapse: collapse; font-size: 13px;">
                            <thead>
                            <tr style="background: {{ config('brand.colors.red') }}; color: #ffffff;">
                                <th align="left" style="padding: 10px 12px; font-weight: 600;">Product</th>
                                <th align="left" style="padding: 10px 12px; font-weight: 600;">Aantal</th>
                                <th align="right" style="padding: 10px 12px; font-weight: 600;">Prijs</th>
                                <th align="right" style="padding: 10px 12px; font-weight: 600;">Subtotaal</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($order->items as $item)
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 10px 12px; color: {{ config('brand.colors.black') }};">{{ $item->product_name }}</td>
                                    <td style="padding: 10px 12px; color: {{ config('brand.colors.muted') }};">{{ $item->quantity }} {{ $item->unit }}</td>
                                    <td align="right" style="padding: 10px 12px; color: {{ config('brand.colors.muted') }};">€ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                                    <td align="right" style="padding: 10px 12px; font-weight: 600; color: {{ config('brand.colors.black') }};">€ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 16px 32px 32px; border-top: 2px solid {{ config('brand.colors.red') }};">
                        <p style="margin: 0; font-size: 16px; font-weight: 700; color: {{ config('brand.colors.black') }}; text-align: right;">
                            Totaal: € {{ number_format($order->total_price, 2, ',', '.') }}
                        </p>
                        <p style="margin: 12px 0 0; font-size: 11px; color: {{ config('brand.colors.muted') }}; text-align: center;">
                            {{ config('brand.name') }} · {{ config('brand.tagline') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
