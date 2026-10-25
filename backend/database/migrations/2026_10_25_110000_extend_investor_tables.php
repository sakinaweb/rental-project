<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 11: a plan belongs to one market (its currency is the country's; only that country's vehicles are allocated),
// and investors give bank details for manual payouts like partners.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_plans', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('name')->constrained();
        });
        Schema::table('investors', function (Blueprint $table) {
            $table->json('payout_details_json')->nullable()->after('risk_ack_at');
        });
    }

    public function down(): void
    {
        Schema::table('investment_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
        });
        Schema::table('investors', function (Blueprint $table) {
            $table->dropColumn('payout_details_json');
        });
    }
};
