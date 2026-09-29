<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $paid = $this->whenLoaded('payments', fn () => $this->amountPaid());

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'expires_at' => $this->status === BookingStatus::Draft
                ? $this->created_at->copy()->addMinutes(Booking::draftTtlMinutes())
                : null,
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email]),
            'vehicle' => $this->whenLoaded('vehicle', fn () => new VehicleResource($this->vehicle)),
            'pickup_location_id' => $this->pickup_location_id,
            'dropoff_location_id' => $this->dropoff_location_id,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'currency_code' => $this->currency_code,
            'exchange_rate' => $this->exchange_rate,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'grand_total' => $this->grand_total,
            'deposit_amount' => $this->deposit_amount,
            'amount_paid' => $paid,
            // Nothing is owed on a cancelled booking; any refund is the payment gateway's job (Phase 4).
            'amount_due' => $this->whenLoaded('payments', fn () => $this->status === BookingStatus::Cancelled
                ? 0
                : $this->grand_total + $this->deposit_amount - $paid),
            'coupon_code' => $this->whenLoaded('coupon', fn () => $this->coupon?->code),
            'addons' => $this->whenLoaded('addons', fn () => $this->addons->map(fn ($line) => [
                'id' => $line->addon_id,
                'name' => $line->addon->name,
                'qty' => $line->qty,
                'price' => $line->price,
            ])),
            'deposit' => $this->whenLoaded('deposit', fn () => $this->deposit ? [
                'status' => $this->deposit->status,
                'amount' => $this->deposit->amount,
                'refunded_amount' => $this->deposit->refunded_amount,
                'refunded_at' => $this->deposit->refunded_at,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
