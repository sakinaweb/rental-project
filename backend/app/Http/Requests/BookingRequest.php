<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use App\Models\Vehicle;
use DomainException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

// Shared by POST /bookings/quote and POST /bookings.
class BookingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $location = Rule::exists('locations', 'id')->whereNull('deleted_at');

        return [
            'vehicle_id' => ['required', 'integer'],
            'start_at' => ['required', 'date', 'after:now'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'addons' => ['nullable', 'array'],
            'addons.*.id' => ['required', 'integer', 'distinct'],
            'addons.*.qty' => ['required', 'integer', 'min:1', 'max:100'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'pickup_location_id' => ['nullable', 'integer', $location],
            'dropoff_location_id' => ['nullable', 'integer', $location],
        ];
    }

    public function vehicle(): Vehicle
    {
        return Vehicle::listed()->findOrFail($this->integer('vehicle_id'));
    }

    /** Stored datetimes are in the app timezone; clients may send any ISO-8601 offset. */
    public function period(): array
    {
        return [
            Carbon::parse($this->string('start_at'))->setTimezone(config('app.timezone')),
            Carbon::parse($this->string('end_at'))->setTimezone(config('app.timezone')),
        ];
    }

    /** @return array<int, int> addon_id => qty */
    public function addons(): array
    {
        return collect($this->input('addons', []))->mapWithKeys(fn (array $a) => [(int) $a['id'] => (int) $a['qty']])->all();
    }

    public function coupon(): ?Coupon
    {
        if (! $this->filled('coupon_code')) {
            return null;
        }

        return Coupon::where('code', $this->string('coupon_code'))->first() ?? throw new DomainException(__('messages.coupon_invalid'));
    }
}
