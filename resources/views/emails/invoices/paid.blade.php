<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factuur {{ $invoice->displayInvoiceNumber() }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: {{ config('brand.colors.surface') }};">

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: {{ config('brand.colors.surface') }}; padding: 32px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 640px; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding: 32px 32px 0;">
                        <x-brand.email-header>
                            Factuur
                        </x-brand.email-header>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 0 32px 8px; font-family: Arial, sans-serif;">
                        <h1 style="margin: 0 0 8px; font-size: 22px; color: {{ config('brand.colors.black') }};">Bedankt voor uw betaling</h1>
                        <p style="margin: 0; font-size: 14px; line-height: 1.6; color: {{ config('brand.colors.muted') }};">
                            Uw bestelling is geleverd en contant voldaan. De factuur vindt u als bijlage bij deze e-mail
                            en staat ook in uw klantportaal.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 20px 32px;">
                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background: {{ config('brand.colors.surface') }}; border-radius: 8px; border-left: 3px solid {{ config('brand.colors.green') }};">
                            <tr>
                                <td style="padding: 16px 20px; font-size: 14px; color: {{ config('brand.colors.black') }};">
                                    <p style="margin: 0 0 6px;"><strong>Factuurnummer:</strong> {{ $invoice->displayInvoiceNumber() }}</p>
                                    <p style="margin: 0 0 6px;"><strong>Ordernummer:</strong> {{ $invoice->order->order_number }}</p>
                                    <p style="margin: 0 0 6px;"><strong>Factuurdatum:</strong> {{ $invoice->invoice_date?->format('d-m-Y') }}</p>
                                    <p style="margin: 0;"><strong>Betaalwijze:</strong> Contant voldaan op {{ $invoice->paid_at?->format('d-m-Y') }}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 0 32px 24px;">
                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                            <tr>
                                <td style="padding: 10px 0; font-size: 14px; color: {{ config('brand.colors.muted') }};">Totaal exclusief btw</td>
                                <td align="right" style="padding: 10px 0; font-size: 14px; color: {{ config('brand.colors.black') }};">
                                    € {{ number_format($invoice->subtotal_amount, 2, ',', '.') }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 10px 0; font-size: 14px; color: {{ config('brand.colors.muted') }};">Btw</td>
                                <td align="right" style="padding: 10px 0; font-size: 14px; color: {{ config('brand.colors.black') }};">
                                    € {{ number_format($invoice->vat_amount, 2, ',', '.') }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 0 0; font-size: 16px; font-weight: bold; color: {{ config('brand.colors.black') }}; border-top: 2px solid {{ config('brand.colors.red') }};">
                                    Voldaan
                                </td>
                                <td align="right" style="padding: 12px 0 0; font-size: 16px; font-weight: bold; color: {{ config('brand.colors.black') }}; border-top: 2px solid {{ config('brand.colors.red') }};">
                                    € {{ number_format($invoice->paid_amount ?? $invoice->total_amount, 2, ',', '.') }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 0 32px 32px; font-size: 12px; line-height: 1.6; color: {{ config('brand.colors.muted') }};">
                        Heeft u een vraag over deze factuur? Neem gerust contact met ons op via {{ config('brand.phone') }}.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
