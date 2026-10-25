<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ComplianceDisclosure;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\InvestmentVehicleAllocation;
use App\Models\Investor;
use App\Models\User;
use App\Models\Vehicle;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Investor module (MD §7.11), behind the global flag and the country pack's feature toggle.
 *
 * Owner decisions (Phase 11): money comes in by bank transfer and the admin confirms it; only company vehicles are
 * funded; each settled booking pays the plan's % of its rental net of tax (the partners' base) out of the platform's
 * share, split between the vehicle's investors by allocated amount; investing needs approved KYC and the current
 * risk disclosure acknowledged.
 */
class InvestorService
{
    /** Bump when the disclosure text (site.investor.disclosure) changes: every investor must accept it again. */
    public const DISCLOSURE_VERSION = '2026-10';

    public function __construct(private CountryPackService $packs) {}

    public function enabledFor(User $user): bool
    {
        return $user->country_id !== null && (bool) ($this->packs->rulesFor($user->country_id)['features']['investor'] ?? false);
    }

    /** @param  array<string, string>  $payoutDetails */
    public function apply(User $user, array $payoutDetails): Investor
    {
        return DB::transaction(function () use ($user, $payoutDetails) {
            // One investor record per user (unique user_id); a rejected applicant may apply again.
            $investor = Investor::withTrashed()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($investor && ! $investor->trashed() && $investor->kyc_status !== Investor::KYC_REJECTED) {
                throw new DomainException(__('messages.investor_already_applied'));
            }
            $investor?->restore();

            return Investor::updateOrCreate(['user_id' => $user->id], ['kyc_status' => Investor::KYC_PENDING, 'payout_details_json' => $payoutDetails]);
        });
    }

    public function acknowledge(Investor $investor): void
    {
        if (! $investor->acknowledged(self::DISCLOSURE_VERSION)) {
            ComplianceDisclosure::create(['investor_id' => $investor->id, 'version' => self::DISCLOSURE_VERSION, 'acknowledged_at' => now()]);
            $investor->update(['risk_ack_at' => now()]);
        }
    }

    /** Plans open to this user: active, in their country. */
    public function openPlans(User $user): Collection
    {
        return InvestmentPlan::with(['country', 'category'])->where('status', InvestmentPlan::ACTIVE)
            ->where('country_id', $user->country_id)->orderBy('min_amount')->get();
    }

    /** A pending investment the investor then pays by bank transfer; the admin activates it on receipt. */
    public function invest(Investor $investor, InvestmentPlan $plan, int $amount): Investment
    {
        if (! $investor->isApproved()) {
            throw new DomainException(__('messages.investor_kyc_required'));
        }
        if (! $investor->acknowledged(self::DISCLOSURE_VERSION)) {
            throw new DomainException(__('messages.investor_disclosure_required'));
        }
        if ($plan->status !== InvestmentPlan::ACTIVE || $plan->country_id !== $investor->user->country_id) {
            throw new DomainException(__('messages.investment_plan_closed'));
        }
        if ($amount < $plan->min_amount) {
            throw new DomainException(__('messages.investment_below_minimum'));
        }

        return Investment::create(['investor_id' => $investor->id, 'plan_id' => $plan->id, 'amount' => $amount, 'status' => Investment::PENDING]);
    }

    /**
     * Admin: the transfer arrived. Places the whole amount on company vehicles of the plan's country (and category,
     * if set) and starts the term now.
     *
     * @param  array<int, int>  $amounts  vehicle_id => allocated amount (minor units)
     */
    public function activate(Investment $investment, array $amounts): Investment
    {
        return DB::transaction(function () use ($investment, $amounts) {
            $investment = Investment::with('plan')->whereKey($investment->id)->lockForUpdate()->firstOrFail();
            if ($investment->status !== Investment::PENDING) {
                throw new DomainException(__('messages.investment_not_pending'));
            }
            $amounts = array_filter($amounts, fn ($a) => $a > 0);
            if ($amounts === [] || array_sum($amounts) !== $investment->amount) {
                throw new DomainException(__('messages.investment_allocation_total'));
            }
            $plan = $investment->plan;
            $eligible = Vehicle::where('owner_type', 'company')->whereKey(array_keys($amounts))
                ->whereHas('branch.location.city', fn ($q) => $q->where('country_id', $plan->country_id))
                ->when($plan->vehicle_category_id, fn ($q, $c) => $q->whereHas('vehicleModel', fn ($m) => $m->where('category_id', $c)))
                ->count();
            if ($eligible !== count($amounts)) {
                throw new DomainException(__('messages.investment_allocation_vehicles'));
            }

            foreach ($amounts as $vehicleId => $amount) {
                $investment->allocations()->create(['vehicle_id' => $vehicleId, 'allocated_amount' => $amount]);
            }
            $investment->update(['status' => Investment::ACTIVE, 'started_at' => now(), 'ends_at' => now()->addMonths($plan->term_months)]);

            return $investment;
        });
    }

    /** Admin: a pending investment that won't be paid in, or an active one whose principal was paid back. */
    public function end(Investment $investment, string $status): Investment
    {
        $from = [Investment::CANCELLED => Investment::PENDING, Investment::CLOSED => Investment::ACTIVE][$status];

        return DB::transaction(function () use ($investment, $status, $from) {
            $investment = Investment::whereKey($investment->id)->lockForUpdate()->firstOrFail();
            if ($investment->status !== $from) {
                throw new DomainException(__('messages.investment_wrong_status'));
            }
            $investment->update(['status' => $status]);

            return $investment;
        });
    }

    /**
     * Each investor's share of a settled booking on a company vehicle: per active investment allocated to the vehicle
     * whose term covers the booking's start, base × its plan % × its part of the vehicle's allocations. Rounded down,
     * so the shares never exceed the plan % and the platform keeps the remainder.
     *
     * @return array<int, int> investor_id => amount (minor units), investors in ascending id (the ledger's lock order)
     */
    public function sharesFor(Booking $booking, int $base): array
    {
        $allocations = InvestmentVehicleAllocation::with('investment.plan')->where('vehicle_id', $booking->vehicle_id)
            ->whereHas('investment', fn ($q) => $q->where('status', Investment::ACTIVE)
                ->where('started_at', '<=', $booking->start_at)->where('ends_at', '>', $booking->start_at))
            ->get();
        $total = $allocations->sum('allocated_amount');

        $shares = [];
        foreach ($allocations as $a) {
            // Integer math in two steps: the plan's pool, then this allocation's part of it.
            // ponytail: pool × allocated must stay under PHP_INT_MAX (9.2e18), e.g. a 1e7 base with 1e11 allocated; use bcmath past that.
            $pool = intdiv($base * (int) round($a->investment->plan->revenue_share_pct * 100), 10000);
            $amount = intdiv($pool * $a->allocated_amount, $total);
            $shares[$a->investment->investor_id] = ($shares[$a->investment->investor_id] ?? 0) + $amount;
        }
        ksort($shares);

        return array_filter($shares);
    }
}
