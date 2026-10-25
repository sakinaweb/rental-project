<?php

namespace App\Livewire;

use App\Models\City;
use App\Models\Country;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\BookingService;
use App\Services\VehicleSearch;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

// Search + results (MD §7.6). Filters and availability come from VehicleSearch (same as GET /vehicles).
#[Layout('layouts.site')]
class Search extends Component
{
    use WithPagination;

    #[Url]
    public ?int $country_id = null;

    #[Url]
    public ?int $city_id = null;

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    #[Url]
    public ?int $category = null;

    #[Url]
    public ?string $type = null;

    #[Url]
    public ?int $seats = null;

    public function updated(string $property): void
    {
        if ($property === 'country_id') {
            $this->city_id = null;
        }
        $this->resetPage();
    }

    /** Quick dates: pick-up tomorrow 10:00, return $days later, so prices show in one click. */
    public function preset(int $days): void
    {
        $from = now()->addDay()->setTime(10, 0);
        $this->from = $from->format('Y-m-d\TH:i');
        $this->to = $from->addDays(max(1, min($days, 30)))->format('Y-m-d\TH:i');
        $this->resetPage();
    }

    public function render(BookingService $bookings, VehicleSearch $search): View
    {
        $filters = array_filter([
            'city_id' => $this->city_id, 'category' => $this->category, 'type' => $this->type, 'seats' => $this->seats,
            'from' => $this->from, 'to' => $this->to,
        ], fn ($v) => $v !== null && $v !== '');

        $validator = Validator::make($filters, VehicleSearch::rules());
        $vehicles = $validator->fails() ? null : $search->query($filters)
            ->when($this->country_id && ! $this->city_id, fn ($q) => $q->whereHas('branch.location.city', fn ($c) => $c->where('country_id', $this->country_id)))
            ->paginate(12);

        $withDates = ! $validator->fails() && isset($filters['from'], $filters['to']);

        return view('livewire.search', [
            'countries' => Country::active()->orderBy('name')->get(),
            'cities' => $this->country_id ? City::where('country_id', $this->country_id)->orderBy('name')->get() : collect(),
            'categories' => VehicleCategory::orderBy('name')->get(),
            'vehicles' => $vehicles,
            'searchErrors' => $validator->errors(),
            'prices' => $withDates && $vehicles ? $vehicles->getCollection()->mapWithKeys(fn (Vehicle $v) => [$v->id => $this->price($bookings, $v)]) : collect(),
        ])->title(__('site.search.title'));
    }

    /** Total for the chosen dates via the real quote; null when the vehicle has no price for them. */
    private function price(BookingService $bookings, Vehicle $vehicle): ?array
    {
        try {
            $quote = $bookings->quote($vehicle, VehicleSearch::toAppTime($this->from), VehicleSearch::toAppTime($this->to));

            return ['amount' => (int) $quote['grand_total'], 'currency' => (string) $quote['currency_code']];
        } catch (DomainException) {
            return null;
        }
    }
}
