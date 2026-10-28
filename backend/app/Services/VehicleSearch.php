<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

// Vehicle search shared by GET /api/v1/vehicles and the customer website (Phase 6), so both filter identically.
class VehicleSearch
{
    public const RELATIONS = ['vehicleModel.brand', 'vehicleModel.category', 'branch.location'];

    public function __construct(private AvailabilityService $availability) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:60'],
            'city_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in(VehicleCategory::TYPES)],
            'brand_id' => ['nullable', 'integer'],
            'transmission' => ['nullable', 'string', 'max:20'],
            'fuel_type' => ['nullable', 'string', 'max:20'],
            'seats' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'required_with:to', 'date'],
            'to' => ['nullable', 'required_with:from', 'date', 'after:from'],
        ];
    }

    /**
     * Listed vehicles matching validated filters; with from/to, vehicles booked in that period are left out.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Vehicle>
     */
    public function query(array $filters): Builder
    {
        return Vehicle::listed()
            ->with(self::RELATIONS)
            ->when($filters['city_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('branch.location', fn (Builder $l) => $l->where('city_id', $id)))
            ->whereHas('vehicleModel', fn (Builder $m) => $m
                ->when($filters['q'] ?? null, fn (Builder $q, string $text) => $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', '%'.addcslashes($text, '%_\\').'%')
                    ->orWhereHas('brand', fn (Builder $b) => $b->where('name', 'like', '%'.addcslashes($text, '%_\\').'%'))))
                ->when($filters['category'] ?? null, fn (Builder $q, $id) => $q->where('category_id', $id))
                ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->whereHas('category', fn (Builder $c) => $c->where('type', $type)))
                ->when($filters['brand_id'] ?? null, fn (Builder $q, $id) => $q->where('brand_id', $id))
                ->when($filters['transmission'] ?? null, fn (Builder $q, $v) => $q->where('transmission', $v))
                ->when($filters['fuel_type'] ?? null, fn (Builder $q, $v) => $q->where('fuel_type', $v))
                ->when($filters['seats'] ?? null, fn (Builder $q, $n) => $q->where('seats', '>=', $n)))
            ->when(isset($filters['from'], $filters['to']), fn (Builder $q) => $q->whereDoesntHave('bookings', fn (Builder $b) => $b->blocking(
                self::toAppTime($filters['from']), self::toAppTime($filters['to']), $this->availability->bufferMinutes(),
            ))->whereDoesntHave('blockouts', fn (Builder $b) => $b->overlapping(self::toAppTime($filters['from']), self::toAppTime($filters['to']))))
            ->orderBy('id');
    }

    /** Stored datetimes are in the app timezone; clients may send any ISO-8601 offset. */
    public static function toAppTime(string|CarbonInterface $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }
}
