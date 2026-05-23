<?php

use App\Http\Controllers\DeliveryPdfController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('/leverbon/{delivery}', [DeliveryPdfController::class, 'download'])
        ->name('leverbon.download');
    Route::get('/factuur/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])
        ->name('invoice.pdf');

    Route::get('/factuur/{invoice}/ubl', [InvoiceController::class, 'downloadUbl'])
        ->name('invoice.ubl');
});

require __DIR__.'/settings.php';
