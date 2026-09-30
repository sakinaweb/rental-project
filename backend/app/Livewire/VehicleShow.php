<?php

namespace App\Livewire;

use App\Http\Requests\BookingRequest;
use App\Models\Addon;
use App\Models\Coupon;
use App\Models\Vehicle;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\VehicleSearch;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

// Vehicle details → availability → quote → book (MD §7.6). All numbers come from AvailabilityService / BookingService.
#[Layout('layouts.site')]
class VehicleShow extends Component
{
    public Vehicle $vehicle;

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    /** @var array<int|string, int|string|null> addon_id => qty */
    #[Url(as: 'addons')]
    public array $qty = [];

    #[Url]
    public string $coupon = '';

    public ?string $bookingError = null;

    public function mount(int $id): void
    {
        $this->vehicle = Vehicle::listed()->with(VehicleSearch::RELATIONS)->findOrFail($id);
    }

    /** Same date rules as POST /bookings (start in the future, end after start). */
    private function periodErrors(): ?string
    {
        $rules = (new BookingRequest)->rules();
        $validator = Validator::make(['start_at' => $this->from, 'end_at' => $this->to], ['start_at' => $rules['start_at'], 'end_at' => $rules['end_at']]);

        return $validator->fails() ? $validator->errors()->first() : null;
    }

    /** @return array<int, int> */
    private function addons(): array
    {
        return collect($this->qty)->map(fn ($q) => (int) $q)->filter(fn (int $q) => $q > 0)->all();
    }

    private function couponModel(): ?Coupon
    {
        if (trim($this->coupon) === '') {
            return null;
        }

        return Coupon::where('code', trim($this->coupon))->first() ?? throw new DomainException(__('messages.coupon_invalid'));
    }

    public function book(BookingService $bookings)
    {
        if (! auth()->check()) {
            session()->put('url.intended', route('vehicles.show', ['id' => $this->vehicle->id, 'from' => $this->from, 'to' => $this->to, 'addons' => $this->qty, 'coupon' => $this->coupon]));

            return $this->redirectRoute('login');
        }
        if ($error = $this->periodErrors()) {
            $this->bookingError = $error;

            return null;
        }

        try {
            $booking = $bookings->create(
                auth()->user(), $this->vehicle, VehicleSearch::toAppTime($this->from), VehicleSearch::toAppTime($this->to),
                $this->addons(), $this->couponModel(),
            );
        } catch (DomainException $e) {
            $this->bookingError = $e->getMessage();

            return null;
        }

        return $this->redirectRoute('checkout', ['booking' => $booking->id]);
    }

    public function render(AvailabilityService $availability, BookingService $bookings): View
    {
        $periodError = ($this->from || $this->to) ? $this->periodErrors() : null;
        $ready = $this->from && $this->to && ! $periodError;
        $quote = $quoteError = null;

        if ($ready) {
            try {
                $quote = $bookings->quote($this->vehicle, VehicleSearch::toAppTime($this->from), VehicleSearch::toAppTime($this->to), $this->addons(), $this->couponModel());
            } catch (DomainException $e) {
                $quoteError = $e->getMessage();
            }
        }

        $model = $this->vehicle->vehicleModel;

        return view('livewire.vehicle-show', [
            'addonList' => Addon::orderBy('name')->get(),
            'periodError' => $periodError,
            'available' => $ready ? $availability->isAvailable($this->vehicle->id, VehicleSearch::toAppTime($this->from), VehicleSearch::toAppTime($this->to)) : null,
            'quote' => $quote,
            'quoteError' => $quoteError,
        ])->title($model->brand->name.' '.$model->name);
    }
}
