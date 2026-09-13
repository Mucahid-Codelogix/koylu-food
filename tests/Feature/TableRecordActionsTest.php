<?php

use Symfony\Component\Finder\Finder;

/**
 * Rij-acties in tabellen staan altijd in één "Acties"-dropdown
 * (TableActionGroup of een groep die daarop voortbouwt), nooit als losse knoppen.
 */
it('zet de rij-acties van elke tabel in één dropdown', function () {
    $offenders = [];

    foreach (Finder::create()->files()->in(app_path('Filament'))->name('*.php') as $file) {
        $contents = $file->getContents();
        $offset = 0;

        while (($position = strpos($contents, '->recordActions(', $offset)) !== false) {
            $start = $position + strlen('->recordActions(');
            $depth = 0;
            $end = $start;

            for ($i = $start, $length = strlen($contents); $i < $length; $i++) {
                $char = $contents[$i];

                if ($char === '(' || $char === '[') {
                    $depth++;
                } elseif ($char === ')' || $char === ']') {
                    $depth--;

                    if ($depth === 0) {
                        $end = $i;
                        break;
                    }
                }
            }

            $block = substr($contents, $start, $end - $start + 1);

            if (! preg_match('/^\[\s*\w*ActionGroup::make\(/', $block)) {
                $offenders[] = $file->getRelativePathname();
            }

            $offset = $end;
        }
    }

    expect($offenders)->toBe([]);
});
