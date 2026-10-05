<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 9 (partner module): what the MD §3 sketch leaves out — partner blockout periods (MD §4
// POST /partner/vehicles/{id}/blockout), which batch a payout request was paid in, and the batch currency.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_blockouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'start_at', 'end_at']);
        });

        Schema::table('payout_batches', function (Blueprint $table) {
            $table->char('currency_code', 3)->after('id');
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->foreignId('payout_batch_id')->nullable()->after('account_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_batch_id');
        });
        Schema::table('payout_batches', function (Blueprint $table) {
            $table->dropColumn('currency_code');
        });
        Schema::dropIfExists('vehicle_blockouts');
    }
};
