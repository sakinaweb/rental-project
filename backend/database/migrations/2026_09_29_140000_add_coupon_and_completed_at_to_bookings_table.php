<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 3 additions to the MD §3 bookings table ("only key columns shown"):
// coupon_id so coupon usage_limit can be enforced and extensions re-priced with the same coupon;
// completed_at so the damage hold window (MD §6) is measured from completion.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('dropoff_location_id')->constrained();
            $table->timestamp('completed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('completed_at');
        });
    }
};
