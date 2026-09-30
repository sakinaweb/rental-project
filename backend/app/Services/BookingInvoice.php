<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;

// Booking invoice PDF (MD §1 DomPDF; decision D4: admin download in Phase 5, customer download with the website).
class BookingInvoice
{
    public function pdf(Booking $booking): string
    {
        // Always render what is stored, not a possibly partial in-memory model.
        $booking = $booking->fresh(['user', 'vehicle.vehicleModel.brand', 'vehicle.branch.location.city.country', 'addons.addon', 'coupon', 'payments']);

        return Pdf::loadView('invoices.booking', ['booking' => $booking])->output();
    }

    public function filename(Booking $booking): string
    {
        return "invoice-booking-{$booking->id}.pdf";
    }
}
