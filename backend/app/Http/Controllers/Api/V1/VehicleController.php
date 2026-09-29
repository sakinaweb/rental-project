<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleCategoryResource;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\AvailabilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    private const RELATIONS = ['vehicleModel.brand', 'vehicleModel.category', 'branch.location'];

    public function index(Request $request, AvailabilityService $availability): AnonymousResourceCollection
    {
        $data = $request->validate([
            'city_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in(VehicleCategory::TYPES)],
            'brand_id' => ['nullable', 'integer'],
            'transmission' => ['nullable', 'string', 'max:20'],
            'fuel_type' => ['nullable', 'string', 'max:20'],
            'seats' => ['nullable', 'integer', 'min:1'],
            ...$this->periodRules(required: false),
        ]);

        $vehicles = Vehicle::listed()
            ->with(self::RELATIONS)
            ->when($data['city_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('branch.location', fn (Builder $l) => $l->where('city_id', $id)))
            ->whereHas('vehicleModel', fn (Builder $m) => $m
                ->when($data['category'] ?? null, fn (Builder $q, $id) => $q->where('category_id', $id))
                ->when($data['type'] ?? null, fn (Builder $q, $type) => $q->whereHas('category', fn (Builder $c) => $c->where('type', $type)))
                ->when($data['brand_id'] ?? null, fn (Builder $q, $id) => $q->where('brand_id', $id))
                ->when($data['transmission'] ?? null, fn (Builder $q, $v) => $q->where('transmission', $v))
                ->when($data['fuel_type'] ?? null, fn (Builder $q, $v) => $q->where('fuel_type', $v))
                ->when($data['seats'] ?? null, fn (Builder $q, $n) => $q->where('seats', '>=', $n)))
            ->when(isset($data['from']), fn (Builder $q) => $q->whereDoesntHave('bookings', fn (Builder $b) => $b->blocking(
                $this->utc($data['from']), $this->utc($data['to']), $availability->bufferMinutes(),
            )))
            ->orderBy('id')
            ->paginate();

        return VehicleResource::collection($vehicles);
    }

    public function show(int $id): VehicleResource
    {
        return new VehicleResource(Vehicle::listed()->with(self::RELATIONS)->findOrFail($id));
    }

    public function availability(Request $request, int $id, AvailabilityService $availability): JsonResponse
    {
        $data = $request->validate($this->periodRules(required: true));
        Vehicle::listed()->findOrFail($id);

        $from = $this->utc($data['from']);
        $to = $this->utc($data['to']);

        return response()->json(['data' => [
            'vehicle_id' => $id,
            'from' => $from,
            'to' => $to,
            'available' => $availability->isAvailable($id, $from, $to),
        ]]);
    }

    public function categories(): AnonymousResourceCollection
    {
        return VehicleCategoryResource::collection(VehicleCategory::orderBy('name')->paginate());
    }

    /** @return array<string, list<string>> */
    private function periodRules(bool $required): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'from' => [$presence, 'required_with:to', 'date'],
            'to' => [$presence, 'required_with:from', 'date', 'after:from'],
        ];
    }

    /** Stored datetimes are in the app timezone; accept any ISO-8601 offset from clients. */
    private function utc(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }
}
