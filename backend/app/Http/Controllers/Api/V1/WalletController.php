<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\WalletTransactionResource;
use App\Models\Country;
use App\Models\WalletTransaction;
use App\Services\PaymentService;
use App\Services\WalletService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    /** GET /wallet (MD §4): balance per currency + paginated transactions, newest first. */
    public function show(Request $request, WalletService $wallet): AnonymousResourceCollection
    {
        $userId = $request->user()->id;
        $balances = collect($wallet->balances($userId))
            ->map(fn (int $balance, string $currency) => ['currency_code' => $currency, 'balance' => $balance])
            ->values();

        return WalletTransactionResource::collection(WalletTransaction::where('user_id', $userId)->latest('id')->paginate())
            ->additional(['balances' => $balances]);
    }

    /** POST /wallet/topup (MD §4): starts a gateway payment; the webhook credits the wallet. */
    public function topup(Request $request, PaymentService $payments): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'currency_code' => ['nullable', 'string', 'size:3', Rule::exists('countries', 'currency_code')->where('is_active', true)->whereNull('deleted_at')],
            'gateway' => ['required', Rule::in(PaymentService::GATEWAYS)],
        ]);

        // Default: the currency of the user's country.
        $currency = $data['currency_code'] ?? Country::whereKey($request->user()->country_id)->value('currency_code')
            ?? throw new DomainException(__('messages.wallet_currency_required'));

        ['payment' => $payment, 'client' => $client] = $payments->createTopup($request->user(), $data['amount'], $currency, $data['gateway']);

        return response()->json(['data' => ['payment' => new PaymentResource($payment->refresh()), 'client' => $client]], 201);
    }
}
