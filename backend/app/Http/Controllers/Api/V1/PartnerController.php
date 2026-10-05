<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\LedgerEntry;
use App\Models\Partner;
use App\Models\PartnerVehicle;
use App\Models\PayoutRequest;
use App\Models\Vehicle;
use App\Models\VehicleBlockout;
use App\Services\PartnerService;
use App\Services\PayoutService;
use App\Services\VehicleSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Partner API (MD §4, Phase 9). Behind auth and the partner feature toggle; money in integer minor units + currency_code.
class PartnerController extends Controller
{
    public function __construct(private PartnerService $partners, private PayoutService $payouts) {}

    /** POST /partner/apply: payout details for manual bank transfers; the admin reviews the application. */
    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payout_details' => ['required', 'array'],
            'payout_details.account_holder' => ['required', 'string', 'max:100'],
            'payout_details.bank_name' => ['required', 'string', 'max:100'],
            'payout_details.account_number' => ['required', 'string', 'max:50'],
            'payout_details.routing_code' => ['nullable', 'string', 'max:50'], // IFSC, IBAN/SWIFT, ABA…
        ]);
        $partner = $this->partners->apply($request->user(), array_filter($data['payout_details'], fn ($v) => $v !== null));

        return response()->json(['data' => ['status' => $partner->status]], 201);
    }

    /** GET /partner/dashboard: status, vehicles with blockouts, balances per currency, recent payout requests. */
    public function dashboard(Request $request): JsonResponse
    {
        $partner = $this->partner($request);
        $vehicles = $partner->partnerVehicles()->with(['vehicle.vehicleModel.brand', 'vehicle.branch', 'vehicle.blockouts' => fn ($q) => $q->where('end_at', '>', now())->orderBy('start_at')])->get();
        $accounts = $partner->accounts()->orderBy('currency_code')->get();

        return response()->json(['data' => [
            'status' => $partner->status,
            'vehicles' => $vehicles->map(fn (PartnerVehicle $pv) => [
                'id' => $pv->vehicle_id,
                'plate_no' => $pv->vehicle->plate_no,
                'title' => $pv->vehicle->vehicleModel->brand->name.' '.$pv->vehicle->vehicleModel->name,
                'branch' => $pv->vehicle->branch->name,
                'status' => $pv->status,
                'commission_pct' => $pv->commission_pct,
                'blockouts' => $pv->vehicle->blockouts->map(fn (VehicleBlockout $b) => [
                    'id' => $b->id, 'start_at' => $b->start_at->toIso8601String(), 'end_at' => $b->end_at->toIso8601String(), 'reason' => $b->reason,
                ]),
            ]),
            'balances' => $accounts->map(fn ($a) => ['currency_code' => $a->currency_code, 'balance' => $a->balance, 'available' => $this->payouts->available($a)]),
            'payout_requests' => PayoutRequest::whereIn('account_id', $accounts->modelKeys())->with('account')->latest('id')->limit(20)->get()
                ->map(fn (PayoutRequest $r) => [
                    'id' => $r->id, 'amount' => $r->amount, 'currency_code' => $r->currency_code, 'status' => $r->status,
                    'requested_at' => $r->requested_at->toIso8601String(), 'processed_at' => $r->processed_at?->toIso8601String(),
                ]),
        ]]);
    }

    /** GET /partner/earnings: the partner's ledger entries, newest first (?currency_code= to narrow). */
    public function earnings(Request $request): JsonResponse
    {
        $partner = $this->partner($request);
        $currency = $request->validate(['currency_code' => ['sometimes', 'string', 'size:3']])['currency_code'] ?? null;
        $page = LedgerEntry::whereIn('account_id', $partner->accounts()->when($currency, fn ($q, $c) => $q->where('currency_code', strtoupper($c)))->pluck('id'))
            ->with('account')->latest('id')->paginate();

        return response()->json($page->through(fn (LedgerEntry $e) => [
            'id' => $e->id, 'booking_id' => $e->booking_id, 'entry_type' => $e->entry_type, 'amount' => $e->amount,
            'running_balance' => $e->running_balance, 'currency_code' => $e->currency_code, 'created_at' => $e->created_at->toIso8601String(),
        ]));
    }

    /** POST /partner/vehicles: listed after admin approval, at a branch in the partner's own country. */
    public function storeVehicle(Request $request): JsonResponse
    {
        $partner = $this->partner($request);
        $data = $request->validate([
            'model_id' => ['required', 'integer', Rule::exists('vehicle_models', 'id')->whereNull('deleted_at')],
            'branch_id' => ['required', 'integer', Rule::in(Branch::whereHas('location.city', fn ($q) => $q->where('country_id', $request->user()->country_id))->pluck('id'))],
            'plate_no' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate_no')],
            'odometer' => ['sometimes', 'integer', 'min:0'],
        ]);
        $pv = $this->partners->addVehicle($partner, $data);

        return response()->json(['data' => ['id' => $pv->vehicle_id, 'status' => $pv->status, 'commission_pct' => $pv->commission_pct]], 201);
    }

    /** POST /partner/vehicles/{id}/blockout: keep a vehicle off the market for a period (not over existing bookings). */
    public function storeBlockout(Request $request, int $vehicle): JsonResponse
    {
        $partner = $this->partner($request);
        $data = $request->validate([
            'start_at' => ['required', 'date', 'after:now'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $blockout = $this->partners->addBlockout($partner, $this->ownVehicle($partner, $vehicle),
            VehicleSearch::toAppTime($data['start_at']), VehicleSearch::toAppTime($data['end_at']), $data['reason'] ?? null);

        return response()->json(['data' => ['id' => $blockout->id, 'start_at' => $blockout->start_at->toIso8601String(), 'end_at' => $blockout->end_at->toIso8601String()]], 201);
    }

    /** DELETE /partner/vehicles/{id}/blockouts/{blockout}: make the period bookable again. */
    public function destroyBlockout(Request $request, int $vehicle, int $blockout): JsonResponse
    {
        $this->ownVehicle($this->partner($request), $vehicle)->blockouts()->findOrFail($blockout)->delete();

        return response()->json(null, 204);
    }

    /** POST /partner/payout-requests: up to the available balance in that currency; paid by the admin in a batch. */
    public function requestPayout(Request $request): JsonResponse
    {
        $data = $request->validate(['currency_code' => ['required', 'string', 'size:3'], 'amount' => ['required', 'integer', 'min:1']]);
        $payout = $this->payouts->requestPayout($this->partner($request), strtoupper($data['currency_code']), $data['amount']);

        return response()->json(['data' => ['id' => $payout->id, 'amount' => $payout->amount, 'currency_code' => $payout->currency_code, 'status' => $payout->status]], 201);
    }

    private function partner(Request $request): Partner
    {
        return Partner::where('user_id', $request->user()->id)->firstOr(fn () => abort(404, __('messages.partner_not_active')));
    }

    /** Another partner's (or the company's) vehicle is a 404, not a 403: its existence isn't the caller's business. */
    private function ownVehicle(Partner $partner, int $id): Vehicle
    {
        return Vehicle::where('owner_type', 'partner')->where('owner_id', $partner->id)->findOrFail($id);
    }
}
