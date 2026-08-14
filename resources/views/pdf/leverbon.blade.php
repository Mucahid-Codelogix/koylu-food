<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Leverbon {{ $order->delivery_note_number ?? $order->order_number }}</title>
    @include('pdf.partials.leverbon-styles')
</head>
<body>
@include('pdf.partials.leverbon-body')
</body>
</html>
