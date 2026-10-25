<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\PartnerVehicle;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Earnings ledger (MD §3, §5). Every balance change is an entry with a running balance, made under a row lock on the
 * account, so balances are always the sum of their entries. Platform accounts use owner_id 0.
 */
class LedgerService
{
    public function __construct(private InvestorService $investors) {}

    /**
     * MD §5 revenue waterfall, posted once when a booking settles (inside settle()'s transaction).
     *
     * Owner decisions (Phase 9): the base is the rental net of tax — grand total after discounts, add-ons included,
     * minus tax; the deposit never counts. On a partner's vehicle the platform keeps the vehicle's commission_pct
     * (rounded to the minor unit) and the partner gets the rest, so the two shares always add up to the base.
     * Interpretation (to confirm): damage charges kept from the deposit go to the vehicle's owner, who pays the repair.
     */
    public function postBookingCompletion(Booking $booking, int $damagesCollected = 0): void
    {
        DB::transaction(function () use ($booking, $damagesCollected) {
            if (LedgerEntry::where('booking_id', $booking->id)->exists()) {
                return; // already posted
            }

            $base = $booking->grand_total - $booking->tax_total;
            $terms = $booking->vehicle()->withTrashed()->value('owner_type') === 'partner'
                ? PartnerVehicle::withTrashed()->where('vehicle_id', $booking->vehicle_id)->first()
                : null;

            // Phase 11: on a company vehicle its investors' shares come out of the platform's (never on partner vehicles).
            $investorShares = $terms ? [] : $this->investors->sharesFor($booking, $base);

            // Platform, then partner, then investors by id: one lock order everywhere, so concurrent settlements can't deadlock.
            $platform = $this->account(LedgerAccount::PLATFORM, 0, $booking->currency_code);
            $partner = $terms ? $this->account(LedgerAccount::PARTNER, $terms->partner_id, $booking->currency_code) : null;

            $platformShare = $terms ? (int) round($base * $terms->commission_pct / 100) : $base - array_sum($investorShares);
            $this->post($platform, $platformShare, LedgerEntry::CREDIT_SHARE, $booking->id);
            if ($partner) {
                $this->post($partner, $base - $platformShare, LedgerEntry::CREDIT_SHARE, $booking->id);
            }
            foreach ($investorShares as $investorId => $share) {
                $this->post($this->account(LedgerAccount::INVESTOR, $investorId, $booking->currency_code), $share, LedgerEntry::CREDIT_SHARE, $booking->id);
            }
            // Damages pay for repairs, so they go to the vehicle's owner: the partner, or the platform (not investors).
            $this->post($partner ?? $platform, $damagesCollected, LedgerEntry::CREDIT_SHARE, $booking->id);
        });
    }

    /** The owner's account in a currency, created on first use and locked for the rest of the transaction. */
    public function account(string $ownerType, int $ownerId, string $currency): LedgerAccount
    {
        $key = ['owner_type' => $ownerType, 'owner_id' => $ownerId, 'currency_code' => $currency];
        LedgerAccount::insertOrIgnore($key + ['balance' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return LedgerAccount::where($key)->lockForUpdate()->firstOrFail();
    }

    /** Signed amount onto a locked account. Zero amounts are skipped; a balance never goes below zero. */
    public function post(LedgerAccount $account, int $amount, string $type, ?int $bookingId = null): ?LedgerEntry
    {
        if ($amount === 0) {
            return null;
        }

        $balance = $account->balance + $amount;
        if ($balance < 0) {
            throw new DomainException(__('messages.ledger_insufficient'));
        }
        $account->update(['balance' => $balance]);

        return LedgerEntry::create([
            'account_id' => $account->id, 'booking_id' => $bookingId, 'entry_type' => $type,
            'amount' => $amount, 'running_balance' => $balance,
        ]);
    }
}
