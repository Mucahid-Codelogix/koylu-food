<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Bestelbevestiging</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f5f5; padding:40px;">

<div style="max-width:700px; margin:auto; background:white; border-radius:8px; padding:30px;">

    <h1>Bedankt voor uw bestelling</h1>

    <p>
        Uw bestelling is succesvol ontvangen.
    </p>

    <p>
        <strong>Ordernummer:</strong>
        {{ $order->order_number }}
    </p>

    <p>
        <strong>Datum:</strong>
        {{ $order->order_date->format('d-m-Y H:i') }}
    </p>

    <hr>

    <table width="100%" cellpadding="10" cellspacing="0">
        <thead>
        <tr style="background:#f3f4f6;">
            <th align="left">Product</th>
            <th align="left">Aantal</th>
            <th align="left">Prijs</th>
            <th align="left">Subtotaal</th>
        </tr>
        </thead>

        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->quantity }} {{ $item->unit }}</td>
                <td>€ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                <td>€ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div style="margin-top:20px;">
        <strong>
            Totaal:
            € {{ number_format($order->total_price, 2, ',', '.') }}
        </strong>
    </div>

</div>

</body>
</html>
