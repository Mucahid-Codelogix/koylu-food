<?php

use Symfony\Component\Finder\Finder;

/**
 * Pijltjes van getalvelden verspringen standaard per 1; decimalen blijven
 * intypbaar via step "any" (Filament numeric() doet dat standaard).
 */
it('gebruikt in Filament-formulieren geen stapgrootte kleiner dan 1', function () {
    $offenders = collect(Finder::create()->files()->in(app_path())->name('*.php'))
        ->filter(fn ($file) => preg_match('/->step\(\s*0\.\d+\s*\)/', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

it('gebruikt in blade-views geen stapgrootte kleiner dan 1', function () {
    $offenders = collect(Finder::create()->files()->in(resource_path('views'))->name('*.blade.php'))
        ->filter(fn ($file) => preg_match('/step="0\.\d+"/', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
