<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Partner;
use App\Models\PartnerVehicle;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBlockout;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

// Partner module (MD §7.9): onboarding, partner vehicles and blockouts. Admin approval happens in the panel.
class PartnerService
{
    public function __construct(private CountryPackService $packs, private AvailabilityService $availability) {}

    /** The global features.partner flag and the user's country pack must both enable it (MD §8: gate the API too). */
    public function enabledFor(User $user): bool
    {
        return $user->country_id !== null && (bool) ($this->packs->rulesFor($user->country_id)['features']['partner'] ?? false);
    }

    /** @param  array<string, string>  $payoutDetails */
    public function apply(User $user, array $payoutDetails): Partner
    {
        return DB::transaction(function () use ($user, $payoutDetails) {
            // One partner record per user (unique user_id); a rejected applicant may apply again.
            $partner = Partner::where('user_id', $user->id)->lockForUpdate()->first();
            if ($partner && $partner->status !== Partner::REJECTED) {
                throw new DomainException(__('messages.partner_already_applied'));
            }

            $partner ??= new Partner(['user_id' => $user->id]);
            $partner->fill(['payout_details_json' => $payoutDetails, 'status' => Partner::PENDING])->save();

            return $partner;
        });
    }

    /**
     * The vehicle joins the fleet at once but is not listed until the admin approves it (owner decision, Phase 9:
     * default commission from settings, which the admin may change on approval).
     *
     * @param  array{model_id: int, branch_id: int, plate_no: string, odometer?: int}  $data
     */
    public function addVehicle(Partner $partner, array $data): PartnerVehicle
    {
        $this->ensureActive($partner);

        return DB::transaction(function () use ($partner, $data) {
            $vehicle = Vehicle::create($data + ['owner_type' => 'partner', 'owner_id' => $partner->id, 'status' => Vehicle::STATUS_AVAILABLE, 'odometer' => 0]);

            return PartnerVehicle::create([
                'partner_id' => $partner->id, 'vehicle_id' => $vehicle->id, 'status' => PartnerVehicle::PENDING,
                'commission_pct' => (float) Setting::valueOf('partner.default_commission_pct', 20),
            ]);
        });
    }

    /** A blockout may not cover an existing booking: the customer keeps it. Serialized with bookings by the vehicle lock. */
    public function addBlockout(Partner $partner, Vehicle $vehicle, CarbonInterface $from, CarbonInterface $to, ?string $reason): VehicleBlockout
    {
        $this->ensureActive($partner);

        return DB::transaction(function () use ($vehicle, $from, $to, $reason) {
            Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            if (Booking::where('vehicle_id', $vehicle->id)->blocking($from, $to, $this->availability->bufferMinutes())->exists()) {
                throw new DomainException(__('messages.partner_blockout_booked'));
            }

            return $vehicle->blockouts()->create(['start_at' => $from, 'end_at' => $to, 'reason' => $reason]);
        });
    }

    public function ensureActive(Partner $partner): void
    {
        if (! $partner->isActive()) {
            throw new DomainException(__('messages.partner_not_active'));
        }
    }
}
