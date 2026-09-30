<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 4 additions to MD §3 ("only key columns shown"):
// - payments.user_id + nullable booking_id: wallet top-ups (POST /wallet/topup) are gateway payments with no booking.
// - wallet_transactions.currency_code: refunds arrive in each booking's currency, so the wallet keeps one balance per currency.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->constrained();
            $table->foreignId('booking_id')->nullable()->change();
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->char('currency_code', 3)->after('amount');
            $table->index(['user_id', 'currency_code', 'id']);
        });
    }

    public function down(): void
    {
        // MySQL let the composite index replace the user_id foreign key's own index, so the FK must go first.
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'currency_code', 'id']);
            $table->dropColumn('currency_code');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable(false)->change();
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
