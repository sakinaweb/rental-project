<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'gateway' => $this->gateway,
            'gateway_ref' => $this->gateway_ref,
            'amount' => $this->amount,
            'currency_code' => $this->currency_code,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
