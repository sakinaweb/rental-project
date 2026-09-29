<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Partners (v1.1): schema only in Phase 1.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->json('payout_details_json')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreign('owner_id')->references('id')->on('partners');
        });

        Schema::create('partner_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained();
            $table->foreignId('vehicle_id')->constrained();
            $table->decimal('commission_pct', 5, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['partner_id', 'vehicle_id']);
        });

        Schema::create('revenue_share_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained();
            $table->foreignId('vehicle_id')->nullable()->constrained();
            $table->json('terms_json');
            $table->timestamp('signed_at')->nullable();
            $table->string('document_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_share_agreements');
        Schema::dropIfExists('partner_vehicles');
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });
        Schema::dropIfExists('partners');
    }
};
