<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Coupon;
use App\Models\PricingRule;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;

class PricingService
{
    public function __construct(private TaxService $tax) {}

    /**
     * Full price breakdown before booking creation (MD §5). All amounts in minor units of the branch country's currency.
     *
     * Rental: the most specific pricing rule scope wins (vehicle > model > category > branch > global).
     * Each unit rule in that scope (hour, day = started 24h block) prices the period; the customer gets the cheaper.
     * Units starting on a weekend get weekend_multiplier; rentals of 30+ days get monthly_discount_pct off.
     *
     * @param  array<int, int>  $addons  addon_id => quantity
     * @param  CarbonInterface|null  $couponCheckedAt  when coupon validity is judged (default now; extensions pass the booking time)
     * @return array<string, int|string>
     */
    public function quote(Vehicle $vehicle, CarbonInterface $from, CarbonInterface $to, array $addons = [], ?Coupon $coupon = null, ?CarbonInterface $couponCheckedAt = null): array
    {
        $vehicle->loadMissing('vehicleModel.category', 'branch.location.city.country');
        $country = $vehicle->branch->location->city->country;

        $hours = max(1, (int) ceil($from->diffInMinutes($to) / 60));
        $days = (int) ceil($hours / 24);

        $best = $this->rulesFor($vehicle)
            ->map(fn (PricingRule $rule) => $this->rental($rule, $from, $hours, $days))
            ->sortBy(fn (array $price) => $price['rental'] - $price['long_rental_discount'])
            ->first() ?? throw new DomainException(__('messages.no_price'));

        ['unit' => $unit, 'units' => $units, 'rental' => $rental, 'long_rental_discount' => $longRentalDiscount] = $best;
        $addonsTotal = $this->addonsTotal($addons, $days);
        $subtotal = $rental + $addonsTotal;
        $couponDiscount = $coupon ? $this->couponDiscount($coupon, $subtotal - $longRentalDiscount, $couponCheckedAt ?? now()) : 0;
        $discountTotal = $longRentalDiscount + $couponDiscount;

        $tax = $this->tax->apply($subtotal - $discountTotal, $country->id, $vehicle->vehicleModel->category->type);

        return [
            'currency_code' => $country->currency_code,
            'unit' => $unit,
            'units' => $units,
            'rental' => $rental,
            'addons_total' => $addonsTotal,
            'subtotal' => $subtotal,
            'long_rental_discount' => $longRentalDiscount,
            'coupon_discount' => $couponDiscount,
            'discount_total' => $discountTotal,
            'tax_total' => $tax['tax'],
            'grand_total' => $tax['gross'],
        ];
    }

    /** @return array{unit: string, units: int, rental: int, long_rental_discount: int} */
    private function rental(PricingRule $rule, CarbonInterface $from, int $hours, int $days): array
    {
        $step = $rule->unit === 'hour' ? 1 : 24;
        $units = (int) ceil($hours / $step);

        $rental = 0;
        for ($i = 0; $i < $units; $i++) {
            $weekend = $from->copy()->addHours($i * $step)->isWeekend();
            $rental += $weekend ? (int) round($rule->base_rate * $rule->weekend_multiplier) : $rule->base_rate;
        }

        return [
            'unit' => $rule->unit,
            'units' => $units,
            'rental' => $rental,
            'long_rental_discount' => $days >= 30 ? (int) round($rental * $rule->monthly_discount_pct / 100) : 0,
        ];
    }

    /** @return Collection<string, PricingRule> rules of the most specific scope that has any, keyed by unit */
    private function rulesFor(Vehicle $vehicle): Collection
    {
        $chain = [
            'vehicle' => $vehicle->id,
            'model' => $vehicle->model_id,
            'category' => $vehicle->vehicleModel->category_id,
            'branch' => $vehicle->branch_id,
            'global' => null,
        ];

        foreach ($chain as $scope => $id) {
            $rules = PricingRule::where('scope_type', $scope)->where('scope_id', $id)->get()->keyBy('unit');
            if ($rules->isNotEmpty()) {
                return $rules;
            }
        }

        return collect();
    }

    /** @param  array<int, int>  $addons */
    private function addonsTotal(array $addons, int $days): int
    {
        $found = Addon::findMany(array_keys($addons))->keyBy('id');

        $total = 0;
        foreach ($addons as $id => $qty) {
            $addon = $found->get($id) ?? throw new DomainException(__('messages.addon_invalid'));
            if ($qty < 1) {
                throw new DomainException(__('messages.addon_invalid'));
            }
            $total += $addon->price * $qty * ($addon->type === Addon::PER_DAY ? $days : 1);
        }

        return $total;
    }

    private function couponDiscount(Coupon $coupon, int $amount, CarbonInterface $at): int
    {
        if (! $coupon->isValidAt($at)) {
            throw new DomainException(__('messages.coupon_invalid'));
        }

        $discount = $coupon->type === Coupon::PERCENT
            ? (int) round($amount * min($coupon->value, 10000) / 10000)
            : $coupon->value;

        return min($discount, $amount);
    }
}
