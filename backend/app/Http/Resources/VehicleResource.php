<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vehicle */
class VehicleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $model = $this->vehicleModel;
        $location = $this->branch?->location;

        return [
            'id' => $this->id,
            'plate_no' => $this->plate_no,
            'status' => $this->status,
            'odometer' => $this->odometer,
            'owner_type' => $this->owner_type,
            'model' => $model ? [
                'id' => $model->id,
                'name' => $model->name,
                'seats' => $model->seats,
                'transmission' => $model->transmission,
                'fuel_type' => $model->fuel_type,
            ] : null,
            'brand' => $model?->brand ? ['id' => $model->brand->id, 'name' => $model->brand->name] : null,
            'category' => $model?->category ? new VehicleCategoryResource($model->category) : null,
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'location' => $location ? [
                    'id' => $location->id,
                    'name' => $location->name,
                    'city_id' => $location->city_id,
                    'lat' => $location->lat,
                    'lng' => $location->lng,
                ] : null,
            ] : null,
        ];
    }
}
