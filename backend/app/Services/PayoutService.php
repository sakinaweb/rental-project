<?php

namespace App\Services;

use App\Models\Investor;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Partner;
use App\Models\PayoutBatch;
use App\Models\PayoutRequest;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Partner payouts (MD §5). Owner decision (Phase 9): manual batches. Requested money stays in the balance but is no
 * longer available; it leaves the ledger (a 'payout' entry) only when the admin marks the batch paid.
 */
class PayoutService
{
    public function __construct(private LedgerService $ledger, private PartnerService $partners) {}

    /** Balance minus requests not yet paid or rejected. */
    public function available(LedgerAccount $account): int
    {
        return $account->balance - (int) $account->payoutRequests()->whereIn('status', PayoutRequest::OPEN)->sum('amount');
    }

    public function requestPayout(Partner $partner, string $currency, int $amount): PayoutRequest
    {
        $this->partners->ensureActive($partner);

        return $this->request(LedgerAccount::PARTNER, $partner->id, $currency, $amount);
    }

    /** Investors are paid from the same manual batches as partners (Phase 11). */
    public function requestInvestorPayout(Investor $investor, string $currency, int $amount): PayoutRequest
    {
        if (! $investor->isApproved()) {
            throw new DomainException(__('messages.investor_kyc_required'));
        }

        return $this->request(LedgerAccount::INVESTOR, $investor->id, $currency, $amount);
    }

    private function request(string $ownerType, int $ownerId, string $currency, int $amount): PayoutRequest
    {
        return DB::transaction(function () use ($ownerType, $ownerId, $currency, $amount) {
            $account = $this->ledger->account($ownerType, $ownerId, $currency); // locked: one request at a time
            if ($amount < 1 || $amount > $this->available($account)) {
                throw new DomainException(__('messages.payout_exceeds_available'));
            }

            return PayoutRequest::create(['account_id' => $account->id, 'amount' => $amount, 'status' => PayoutRequest::PENDING, 'requested_at' => now()]);
        });
    }

    public function reject(PayoutRequest $request): PayoutRequest
    {
        return DB::transaction(function () use ($request) {
            $request = PayoutRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== PayoutRequest::PENDING) {
                throw new DomainException(__('messages.payout_request_not_pending'));
            }
            $request->update(['status' => PayoutRequest::REJECTED, 'processed_at' => now()]);

            return $request;
        });
    }

    /** All pending requests in one currency into a new batch (MD §4 POST /admin/payout-batches). */
    public function createBatch(string $currency, User $admin): PayoutBatch
    {
        return DB::transaction(function () use ($currency, $admin) {
            $requests = PayoutRequest::where('status', PayoutRequest::PENDING)
                ->whereHas('account', fn ($q) => $q->where('currency_code', $currency))
                ->orderBy('id')->lockForUpdate()->get();
            if ($requests->isEmpty()) {
                throw new DomainException(__('messages.payout_none_pending'));
            }

            $batch = PayoutBatch::create([
                'currency_code' => $currency, 'total_amount' => $requests->sum('amount'),
                'status' => PayoutBatch::PENDING, 'processed_by' => $admin->id,
            ]);
            PayoutRequest::whereKey($requests->modelKeys())->update(['status' => PayoutRequest::BATCHED, 'payout_batch_id' => $batch->id]);

            return $batch;
        });
    }

    /** MD §5 processBatch: after the bank transfers, each request leaves its partner's balance as a 'payout' entry. */
    public function markPaid(PayoutBatch $batch, User $admin): PayoutBatch
    {
        return $this->close($batch, $admin, function (PayoutBatch $batch) {
            foreach ($batch->requests()->orderBy('account_id')->get() as $request) {
                $account = LedgerAccount::whereKey($request->account_id)->lockForUpdate()->firstOrFail();
                $this->ledger->post($account, -$request->amount, LedgerEntry::PAYOUT);
                $request->update(['status' => PayoutRequest::PAID, 'processed_at' => now()]);
            }

            return PayoutBatch::PAID;
        });
    }

    /** A transfer that didn't happen: the requests go back to pending for a later batch. */
    public function cancel(PayoutBatch $batch, User $admin): PayoutBatch
    {
        return $this->close($batch, $admin, function (PayoutBatch $batch) {
            $batch->requests()->update(['status' => PayoutRequest::PENDING, 'payout_batch_id' => null]);

            return PayoutBatch::CANCELLED;
        });
    }

    /** @param  callable(PayoutBatch): string  $apply returns the batch's new status */
    private function close(PayoutBatch $batch, User $admin, callable $apply): PayoutBatch
    {
        return DB::transaction(function () use ($batch, $admin, $apply) {
            $batch = PayoutBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== PayoutBatch::PENDING) {
                throw new DomainException(__('messages.payout_batch_not_pending'));
            }
            $batch->update(['status' => $apply($batch), 'processed_by' => $admin->id, 'processed_at' => now()]);

            return $batch;
        });
    }
}
