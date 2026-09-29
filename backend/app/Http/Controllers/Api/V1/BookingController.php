<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    private const RELATIONS = ['vehicle.vehicleModel.brand', 'vehicle.vehicleModel.category', 'vehicle.branch.location', 'addons.addon', 'coupon', 'deposit', 'payments'];

    public function __construct(private BookingService $bookings) {}

    /**
     * Pricing quote endpoint (MD §7.3). Public: same input as creating a booking.
     * The path POST /bookings/quote is an implementation choice: the MD requires the endpoint but §4 gives no path.
     */
    public function quote(BookingRequest $request): JsonResponse
    {
        [$from, $to] = $request->period();

        return response()->json(['data' => $this->bookings->quote($request->vehicle(), $from, $to, $request->addons(), $request->coupon())]);
    }

    public function store(BookingRequest $request): BookingResource
    {
        [$from, $to] = $request->period();

        $booking = $this->bookings->create(
            $request->user(), $request->vehicle(), $from, $to, $request->addons(), $request->coupon(),
            $request->integer('pickup_location_id') ?: null, $request->integer('dropoff_location_id') ?: null,
        );

        return new BookingResource($booking->load(self::RELATIONS));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return BookingResource::collection($request->user()->bookings()->with(self::RELATIONS)->latest('id')->paginate());
    }

    public function show(Booking $booking): BookingResource
    {
        Gate::authorize('view', $booking);

        return new BookingResource($booking->load(self::RELATIONS));
    }

    public function confirm(Booking $booking): BookingResource
    {
        Gate::authorize('update', $booking);

        return new BookingResource($this->bookings->confirm($booking)->load(self::RELATIONS));
    }

    public function cancel(Booking $booking): BookingResource
    {
        Gate::authorize('update', $booking);

        return new BookingResource($this->bookings->cancel($booking)->load(self::RELATIONS));
    }

    public function extend(Request $request, Booking $booking): BookingResource
    {
        Gate::authorize('update', $booking);
        $request->validate(['end_at' => ['required', 'date', 'after:'.$booking->end_at->toIso8601String()]]);

        $newEnd = Carbon::parse($request->string('end_at'))->setTimezone(config('app.timezone'));

        return new BookingResource($this->bookings->extend($booking, $newEnd)->load(self::RELATIONS));
    }
}
