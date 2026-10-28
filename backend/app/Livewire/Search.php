<?php

namespace App\Livewire;

use App\Models\City;
use App\Models\Country;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\BookingService;
use App\Services\VehicleSearch;
use App\Support\Money;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
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

    #[Url]
    public ?string $q = null;

    /** Highest price per day, in major units of each vehicle's own currency. */
    #[Url]
    public ?int $max_price = null;

    #[Url]
    public string $sort = 'recommended';

    public const SORTS = ['recommended', 'price_asc', 'price_desc', 'newest'];

    public function updated(string $property): void
    {
        if ($property === 'country_id') {
            $this->city_id = null;
        }
        if ($property === 'type') {
            $this->category = null;
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
            'q' => $this->q !== null ? trim($this->q) : null,
            'city_id' => $this->city_id, 'category' => $this->category, 'type' => $this->type, 'seats' => $this->seats,
            'from' => $this->from, 'to' => $this->to,
        ], fn ($v) => $v !== null && $v !== '');

        $validator = Validator::make($filters, VehicleSearch::rules());
        $withDates = ! $validator->fails() && isset($filters['from'], $filters['to']);
        $from = $withDates ? VehicleSearch::toAppTime($this->from) : now()->addDay()->setTime(10, 0);
        $to = $withDates ? VehicleSearch::toAppTime($this->to) : $from->copy()->addDay();

        $cards = null;
        if (! $validator->fails()) {
            // One card per model and branch: the first free vehicle is booked, the rest only count as "N available".
            // ponytail: grouping, price filter and sort run in PHP over all matches (a real quote each); fine for a few
            // hundred vehicles per search, move prices into SQL if a fleet grows past that.
            $cards = $search->query($filters)
                ->when($this->country_id && ! $this->city_id, fn ($q) => $q->whereHas('branch.location.city', fn ($c) => $c->where('country_id', $this->country_id)))
                ->get()
                ->groupBy(fn (Vehicle $v) => $v->model_id.'-'.$v->branch_id)
                ->map(fn ($group) => ['vehicle' => $group->first(), 'count' => $group->count(), 'price' => $this->price($bookings, $group->first(), $from, $to)])
                ->when($this->max_price, fn ($c) => $c->filter(fn (array $card) => $card['price']
                    && $card['price']['per_day'] <= $this->max_price * 10 ** Money::digits($card['price']['currency'])))
                ->values();

            $cards = match ($this->sort) {
                'price_asc' => $cards->sortBy(fn (array $c) => data_get($c, 'price.per_day', PHP_INT_MAX)),
                'price_desc' => $cards->sortByDesc(fn (array $c) => data_get($c, 'price.per_day', -1)),
                'newest' => $cards->sortByDesc(fn (array $c) => $c['vehicle']->model_id),
                default => $cards,
            };
            $page = $this->getPage();
            $cards = new LengthAwarePaginator($cards->forPage($page, 12)->values(), $cards->count(), 12, $page, ['path' => route('home'), 'pageName' => 'page']);
        }

        return view('livewire.search', [
            'countries' => Country::active()->orderBy('name')->get(),
            'cities' => $this->country_id ? City::where('country_id', $this->country_id)->orderBy('name')->get() : collect(),
            'categories' => VehicleCategory::orderBy('type')->orderBy('name')->get(),
            'cards' => $cards,
            'withDates' => $withDates,
            'searchErrors' => $validator->errors(),
        ])->title(__('site.search.title'));
    }

    /**
     * Real quote for the chosen dates (or one day from tomorrow 10:00 when none are chosen), with the price per day
     * used to filter and sort; null when the vehicle has no price for that period.
     *
     * @return array{total: int, per_day: int, currency: string}|null
     */
    private function price(BookingService $bookings, Vehicle $vehicle, CarbonInterface $from, CarbonInterface $to): ?array
    {
        try {
            $quote = $bookings->quote($vehicle, $from, $to);
        } catch (DomainException) {
            return null;
        }
        $days = max(1, $from->diffInHours($to) / 24);

        return ['total' => (int) $quote['grand_total'], 'per_day' => (int) round($quote['grand_total'] / $days), 'currency' => (string) $quote['currency_code']];
    }
}
