<?php

return [

    'client_id' => env('EXACT_CLIENT_ID'),

    'client_secret' => env('EXACT_CLIENT_SECRET'),

    'redirect_uri' => env('EXACT_REDIRECT_URI') ?: rtrim((string) env('APP_URL', 'http://localhost'), '/').'/exact/oauth/callback',

    'division' => env('EXACT_DIVISION') ? (int) env('EXACT_DIVISION') : null,

    'base_url' => env('EXACT_BASE_URL', 'https://start.exactonline.nl'),

    'retry' => [
        'max_attempts' => 3,
        'initial_delay_seconds' => 1,
    ],

    'customer' => [
        'search_code_prefix' => env('EXACT_CUSTOMER_SEARCH_CODE_PREFIX', 'KOYLU'),
        'vat_codes' => [
            'nl' => env('EXACT_CUSTOMER_VAT_CODE_NL'),
            'be' => env('EXACT_CUSTOMER_VAT_CODE_BE'),
            'exempt' => env('EXACT_CUSTOMER_VAT_CODE_EXEMPT'),
        ],
    ],

    'item' => [
        'code_prefix' => env('EXACT_ITEM_CODE_PREFIX', 'KOYLU'),
        'unit' => env('EXACT_ITEM_UNIT', 'kg'),
        'item_group' => env('EXACT_ITEM_GROUP'),
        'vat_codes' => [
            'low' => env('EXACT_ITEM_VAT_CODE_LOW'),
            'high' => env('EXACT_ITEM_VAT_CODE_HIGH'),
        ],
    ],

    'supplier' => [
        'search_code_prefix' => env('EXACT_SUPPLIER_SEARCH_CODE_PREFIX', 'KOYLU-S'),
        'vat_code' => env('EXACT_SUPPLIER_VAT_CODE'),
    ],

    'invoice' => [
        'journal' => env('EXACT_INVOICE_JOURNAL', '70'),
        'print_on_push' => env('EXACT_INVOICE_PRINT_ON_PUSH', true),
        'vat_codes' => [
            '0.00' => env('EXACT_INVOICE_VAT_CODE_EXEMPT'),
            '9.00' => env('EXACT_INVOICE_VAT_CODE_LOW'),
            '21.00' => env('EXACT_INVOICE_VAT_CODE_HIGH'),
        ],
    ],

    'alerts' => [
        'mail_to' => env('EXACT_ALERT_MAIL_TO'),
        'failure_threshold' => (int) env('EXACT_ALERT_FAILURE_THRESHOLD', 3),
    ],

];
