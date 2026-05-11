<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class DeliveryPdfController extends Controller
{
    public function download(Delivery $delivery): Response
    {
        $delivery->load([
            'order.customer',
            'order.items.product',
            'items.orderItem',
        ]);

        // Klant mag alleen zijn eigen leverbon downloaden
        $user = auth()->user();
        if ($user->role === 'customer') {
            abort_unless(
                $delivery->order->customer_id === $user->customer_id,
                403
            );
        }

        $pdf = Pdf::loadView('pdf.leverbon', compact('delivery'))
            ->setPaper('a4', 'portrait');

        $filename = 'leverbon-'.$delivery->order->order_number.'.pdf';

        return $pdf->download($filename);
    }
}
