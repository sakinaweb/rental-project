<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /** POST /payments/intent (MD §4): pay what is due on a booking with a gateway or the wallet. */
    public function intent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer'],
            'gateway' => ['required', Rule::in([...PaymentService::GATEWAYS, Payment::WALLET])],
        ]);
        $booking = Booking::findOrFail($data['booking_id']);
        Gate::authorize('update', $booking);

        ['payment' => $payment, 'client' => $client] = $this->payments->createBookingIntent($booking, $data['gateway']);

        return response()->json(['data' => [
            'payment' => new PaymentResource($payment->refresh()),
            'client' => $client,
            'booking_status' => $booking->refresh()->status->value,
        ]], 201);
    }

    /**
     * GET /payments/gateways (Phase 7): card gateways the app may offer; the wallet is always available.
     * ?currency= (Phase 8) narrows them to what that currency's country pack allows.
     */
    public function gateways(Request $request): JsonResponse
    {
        $currency = $request->validate(['currency' => ['sometimes', 'string', 'size:3']])['currency'] ?? null;

        return response()->json(['data' => $currency
            ? $this->payments->gatewaysFor(strtoupper($currency))
            : $this->payments->configuredGateways()]);
    }

    /** POST /payments/webhook/{gateway} (MD §4): signature verified by the driver; always 200 once accepted. */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        $this->payments->handleWebhook($gateway, $request);

        return response()->json(['received' => true]);
    }
}
