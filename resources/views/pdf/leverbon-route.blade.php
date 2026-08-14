<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Leverbonnen route {{ $route->route_date?->format('d-m-Y') }}</title>
    @include('pdf.partials.leverbon-styles')
</head>
<body>
@foreach ($notes as $note)
    @include('pdf.partials.leverbon-body', [
        'order' => $note['order'],
        'delivery' => $note['delivery'],
        'isFinal' => $note['isFinal'],
        'lines' => $note['lines'],
        'totals' => $note['totals'],
        'crates' => $note['crates'],
        'signatures' => $note['signatures'],
        'pageBreak' => ! $loop->last,
    ])
@endforeach
</body>
</html>
