@php
    $brandRed = config('brand.colors.red');
@endphp

<style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 13px;
            color: #1a1a1a;
            background: #fff;
        }

        .page {
            padding: 36px 40px;
        }

        /* Header */
        .header {
            width: 100%;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid {{ $brandRed }};
        }

        .header td {
            vertical-align: top;
            padding: 0;
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
            font-size: 15px;
            font-weight: 700;
            color: {{ $brandRed }};
            margin-top: 4px;
        }

        .doc-title .doc-sub {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* Info blokken — tabel-layout, want DomPDF ondersteunt flexbox niet betrouwbaar */
        .layout-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin: 0 0 24px -10px;
        }

        .layout-table td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }

        .info-block {
            background: #f9fafb;
            border-radius: 8px;
            padding: 14px;
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
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 99px;
        }

        .status-badge-final {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-badge-concept {
            background: #fef3c7;
            color: #b45309;
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
            margin-bottom: 24px;
        }

        thead tr {
            background: {{ $brandRed }};
        }

        thead th {
            padding: 9px 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        thead th.num,
        tbody td.num,
        tfoot td.num { text-align: right; }

        tbody tr {
            border-bottom: 1px solid #f3f4f6;
        }

        tbody tr:nth-child(even) {
            background: #fafafa;
        }

        tbody td {
            padding: 9px 12px;
            font-size: 12px;
            color: #374151;
        }

        .missed {
            color: #dc2626;
            font-style: italic;
        }

        .deviation {
            font-size: 10px;
            color: #d97706;
        }

        .muted {
            font-size: 10px;
            color: #9ca3af;
        }

        tfoot td {
            padding: 9px 12px;
            font-size: 12px;
            font-weight: 700;
            border-top: 2px solid {{ $brandRed }};
        }

        /* Kratten */
        .crate-block {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px;
        }

        .crate-label {
            font-size: 11px;
            font-weight: 700;
            color: #374151;
        }

        .crate-value {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a1a;
            margin-top: 6px;
        }

        .fill-box {
            margin-top: 8px;
            height: 36px;
            border: 1px dashed #9ca3af;
            border-radius: 6px;
            background: #fff;
        }

        /* Handtekening */
        .signature-block {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px;
        }

        .signature-block-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        .signature-block img {
            max-height: 76px;
            max-width: 100%;
        }

        .signature-block p {
            font-size: 12px;
            color: #374151;
            margin-top: 6px;
        }

        .sign-area {
            height: 76px;
            border-bottom: 1px solid #9ca3af;
        }

        .sign-line {
            margin-top: 10px;
            font-size: 10px;
            color: #9ca3af;
        }

        .sign-line span {
            display: inline-block;
            border-bottom: 1px solid #d1d5db;
            width: 60%;
        }

        /* Footer pagina */
        .page-footer {
            margin-top: 32px;
            padding-top: 14px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }
    </style>
