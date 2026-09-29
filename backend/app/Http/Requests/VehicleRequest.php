<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'model_id' => ['required', 'integer', Rule::exists('vehicle_models', 'id')->whereNull('deleted_at')],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'owner_type' => ['required', Rule::in(Vehicle::OWNER_TYPES)],
            'owner_id' => ['nullable', 'integer', 'required_if:owner_type,partner', 'prohibited_if:owner_type,company', Rule::exists('partners', 'id')->whereNull('deleted_at')],
            // unique across soft-deleted rows too, matching the DB unique index
            'plate_no' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate_no')->ignore($this->route('vehicle'))],
            'status' => ['required', Rule::in(Vehicle::STATUSES)],
            'odometer' => ['required', 'integer', 'min:0'],
        ];
    }
}
