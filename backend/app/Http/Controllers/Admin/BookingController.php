<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    /** POST /admin/bookings/{id}/assign (decision D2): move the booking to another vehicle. */
    public function assign(Request $request, Booking $booking, BookingService $bookings): BookingResource
    {
        $data = $request->validate(['vehicle_id' => ['required', 'integer']]);
        $from = $booking->vehicle_id;

        $booking = $bookings->reassign($booking, Vehicle::findOrFail($data['vehicle_id']));
        AuditLog::record('booking.assign', $booking, ['from_vehicle_id' => $from, 'to_vehicle_id' => $booking->vehicle_id]);

        return new BookingResource($booking->load(['user', 'vehicle.vehicleModel.brand', 'deposit', 'payments']));
    }

    public function index(): AnonymousResourceCollection
    {
        return BookingResource::collection(
            Booking::with(['user', 'vehicle.vehicleModel.brand', 'deposit', 'payments'])->latest('id')->paginate(),
        );
    }
}
