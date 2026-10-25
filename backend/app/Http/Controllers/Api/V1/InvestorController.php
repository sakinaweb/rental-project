<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\InvestmentVehicleAllocation;
use App\Models\Investor;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\PayoutRequest;
use App\Services\InvestorService;
use App\Services\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

// Investor API (MD §4, Phase 11). Behind auth and the investor feature toggle; money in integer minor units + currency_code.
class InvestorController extends Controller
{
    public function __construct(private InvestorService $investors, private PayoutService $payouts) {}

    /** GET /investment-plans: plans open in the user's country. */
    public function plans(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->investors->openPlans($request->user())->map(fn (InvestmentPlan $p) => [
            'id' => $p->id, 'name' => $p->name, 'currency_code' => $p->currency_code, 'min_amount' => $p->min_amount,
            'term_months' => $p->term_months, 'revenue_share_pct' => $p->revenue_share_pct, 'category' => $p->category?->name,
        ])]);
    }

    /** POST /investor/apply: bank details for payouts; the admin checks identity (KYC) and approves. */
    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payout_details' => ['required', 'array'],
            'payout_details.account_holder' => ['required', 'string', 'max:100'],
            'payout_details.bank_name' => ['required', 'string', 'max:100'],
            'payout_details.account_number' => ['required', 'string', 'max:50'],
            'payout_details.routing_code' => ['nullable', 'string', 'max:50'], // IFSC, IBAN/SWIFT, ABA…
        ]);
        $investor = $this->investors->apply($request->user(), array_filter($data['payout_details'], fn ($v) => $v !== null));

        return response()->json(['data' => ['kyc_status' => $investor->kyc_status]], 201);
    }

    /** GET /investor/disclosure: the risk disclosure to accept, in the session language. */
    public function disclosure(): JsonResponse
    {
        return response()->json(['data' => ['version' => InvestorService::DISCLOSURE_VERSION, 'text' => __('site.investor.disclosure')]]);
    }

    /** POST /investor/disclosure: accept the current version (stored with the time, as evidence). */
    public function acknowledge(Request $request): JsonResponse
    {
        $request->validate(['version' => ['required', 'string', 'in:'.InvestorService::DISCLOSURE_VERSION]]);
        $this->investors->acknowledge($this->investor($request));

        return response()->json(['data' => ['version' => InvestorService::DISCLOSURE_VERSION]], 201);
    }

    /** POST /investments: pending until the admin confirms the bank transfer. */
    public function invest(Request $request): JsonResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'integer'], 'amount' => ['required', 'integer', 'min:1']]);
        $investment = $this->investors->invest($this->investor($request), InvestmentPlan::findOrFail($data['plan_id']), $data['amount']);

        return response()->json(['data' => $this->investment($investment->load('plan.country'))], 201);
    }

    /** GET /investor/dashboard: status, investments with their vehicles, balances per currency, recent payout requests. */
    public function dashboard(Request $request): JsonResponse
    {
        $investor = $this->investor($request);
        $accounts = $investor->accounts()->orderBy('currency_code')->get();

        return response()->json(['data' => [
            'kyc_status' => $investor->kyc_status,
            'disclosure_accepted' => $investor->acknowledged(InvestorService::DISCLOSURE_VERSION),
            'investments' => $investor->investments()->with(['plan.country', 'allocations.vehicle.vehicleModel.brand'])->latest('id')->get()
                ->map(fn (Investment $i) => $this->investment($i)),
            'balances' => $accounts->map(fn (LedgerAccount $a) => ['currency_code' => $a->currency_code, 'balance' => $a->balance, 'available' => $this->payouts->available($a)]),
            'payout_requests' => PayoutRequest::whereIn('account_id', $accounts->modelKeys())->with('account')->latest('id')->limit(20)->get()
                ->map(fn (PayoutRequest $r) => [
                    'id' => $r->id, 'amount' => $r->amount, 'currency_code' => $r->currency_code, 'status' => $r->status,
                    'requested_at' => $r->requested_at->toIso8601String(), 'processed_at' => $r->processed_at?->toIso8601String(),
                ]),
        ]]);
    }

    /** GET /investor/statements/{period}: one month (YYYY-MM) per currency — opening and closing balance, and its entries. */
    public function statement(Request $request, string $period): JsonResponse
    {
        $investor = $this->investor($request);
        $from = Carbon::createFromFormat('!Y-m', $period);
        abort_unless($from && $from->format('Y-m') === $period, 404);
        $to = $from->copy()->addMonth();

        return response()->json(['data' => [
            'period' => $period,
            'accounts' => $investor->accounts()->orderBy('currency_code')->get()->map(function (LedgerAccount $a) use ($from, $to) {
                $entries = $a->entries()->where('created_at', '>=', $from)->where('created_at', '<', $to)->orderBy('id')->get();
                $opening = (int) $a->entries()->where('created_at', '<', $from)->sum('amount');

                return [
                    'currency_code' => $a->currency_code,
                    'opening_balance' => $opening,
                    'earned' => (int) $entries->where('entry_type', LedgerEntry::CREDIT_SHARE)->sum('amount'),
                    'paid_out' => (int) -$entries->where('entry_type', LedgerEntry::PAYOUT)->sum('amount'),
                    'closing_balance' => $opening + (int) $entries->sum('amount'),
                    'entries' => $entries->map(fn (LedgerEntry $e) => [
                        'id' => $e->id, 'booking_id' => $e->booking_id, 'entry_type' => $e->entry_type, 'amount' => $e->amount,
                        'running_balance' => $e->running_balance, 'created_at' => $e->created_at->toIso8601String(),
                    ]),
                ];
            }),
        ]]);
    }

    /** POST /investor/payout-requests: paid in the admin's next manual batch, like partners. */
    public function requestPayout(Request $request): JsonResponse
    {
        $data = $request->validate(['currency_code' => ['required', 'string', 'size:3'], 'amount' => ['required', 'integer']]);
        $payout = $this->payouts->requestInvestorPayout($this->investor($request), strtoupper($data['currency_code']), $data['amount']);

        return response()->json(['data' => ['id' => $payout->id, 'amount' => $payout->amount, 'status' => $payout->status]], 201);
    }

    private function investor(Request $request): Investor
    {
        return Investor::where('user_id', $request->user()->id)->firstOr(fn () => abort(404, __('messages.investor_not_found')));
    }

    /** @return array<string, mixed> */
    private function investment(Investment $i): array
    {
        return [
            'id' => $i->id, 'status' => $i->status, 'amount' => $i->amount, 'currency_code' => $i->plan->currency_code,
            'plan' => ['id' => $i->plan->id, 'name' => $i->plan->name, 'revenue_share_pct' => $i->plan->revenue_share_pct, 'term_months' => $i->plan->term_months],
            'started_at' => $i->started_at?->toIso8601String(), 'ends_at' => $i->ends_at?->toIso8601String(),
            'vehicles' => $i->relationLoaded('allocations') ? $i->allocations->map(fn (InvestmentVehicleAllocation $a) => [
                'plate_no' => $a->vehicle->plate_no, 'title' => $a->vehicle->vehicleModel->brand->name.' '.$a->vehicle->vehicleModel->name,
                'allocated_amount' => $a->allocated_amount,
            ]) : [],
        ];
    }
}
