<?php

use App\Enums\VatCategory;
use App\Services\Exact\ExactItemToProductMapper;
use Picqer\Financials\Exact\Connection;
use Picqer\Financials\Exact\Item;

it('maps an exact item to product attributes', function () {
    config(['exact.item.vat_codes.low' => '1']);

    $item = new Item(new Connection);
    $item->ID = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    $item->Code = 'KL-100';
    $item->Description = 'Kippenlever';
    $item->SalesVatCode = '1';

    expect(ExactItemToProductMapper::toProductAttributes($item))->toMatchArray([
        'name' => 'Kippenlever',
        'exact_article_code' => 'KL-100',
        'vat_category' => VatCategory::Low,
        'exact_sync_error' => null,
        'is_active' => true,
    ]);
});

it('defaults to high vat when the exact sales vat code is unknown', function () {
    config([
        'exact.item.vat_codes.low' => '1',
        'exact.item.vat_codes.high' => '2',
    ]);

    $item = new Item(new Connection);
    $item->Code = 'KH-100';
    $item->Description = 'Kippendijen';
    $item->SalesVatCode = '2';

    expect(ExactItemToProductMapper::vatCategory($item))->toBe(VatCategory::High);
});
