<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// POST /admin/bookings/{id}/assign is deferred to Phase 5 (decision: what "assign" means is unspecified).
class BookingController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BookingResource::collection(
            Booking::with(['user', 'vehicle.vehicleModel.brand', 'deposit', 'payments'])->latest('id')->paginate(),
        );
    }
}
