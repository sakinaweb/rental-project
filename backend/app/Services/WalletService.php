<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use DomainException;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /** Locking read: inside a longer transaction it still sees the latest committed balance, not an old snapshot. */
    public function balance(int $userId, string $currency): int
    {
        return (int) WalletTransaction::where('user_id', $userId)
            ->where('currency_code', $currency)
            ->latest('id')
            ->sharedLock()
            ->value('balance_after');
    }

    /** @return array<string, int> currency => balance */
    public function balances(int $userId): array
    {
        return WalletTransaction::where('user_id', $userId)->distinct()->orderBy('currency_code')->pluck('currency_code')
            ->mapWithKeys(fn (string $currency) => [$currency => $this->balance($userId, $currency)])
            ->all();
    }

    public function credit(int $userId, int $amount, string $currency, string $type, string $referenceType, int $referenceId): WalletTransaction
    {
        return $this->post($userId, $amount, $currency, $type, $referenceType, $referenceId);
    }

    public function debit(int $userId, int $amount, string $currency, string $type, string $referenceType, int $referenceId): WalletTransaction
    {
        return $this->post($userId, -$amount, $currency, $type, $referenceType, $referenceId);
    }

    /** The user row lock serializes all balance changes for that user, so balance_after is always correct. */
    private function post(int $userId, int $amount, string $currency, string $type, string $referenceType, int $referenceId): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amount, $currency, $type, $referenceType, $referenceId) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $balance = $this->balance($userId, $currency) + $amount;

            if ($balance < 0) {
                throw new DomainException(__('messages.wallet_insufficient'));
            }

            return WalletTransaction::create([
                'user_id' => $userId,
                'type' => $type,
                'amount' => $amount,
                'currency_code' => $currency,
                'balance_after' => $balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }
}
