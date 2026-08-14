<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Exact geeft bij elke tokenvernieuwing een nieuwe refresh-token. Blijft die te
 * lang ongebruikt, dan verloopt de koppeling en moet er handmatig opnieuw
 * ingelogd worden. Dagelijks vernieuwen voorkomt dat.
 */
Schedule::command('exact:refresh-connection')
    ->dailyAt('06:00')
    ->withoutOverlapping();
