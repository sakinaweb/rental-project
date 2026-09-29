<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Investors (v1.2, feature-flagged): schema only in Phase 1.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->string('kyc_status', 20)->default('pending');
            $table->timestamp('risk_ack_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('investment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('vehicle_category_id')->nullable()->constrained();
            $table->unsignedBigInteger('min_amount');
            $table->unsignedSmallInteger('term_months');
            $table->decimal('revenue_share_pct', 5, 2);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->constrained();
            $table->foreignId('plan_id')->constrained('investment_plans');
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('investment_vehicle_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained();
            $table->unsignedBigInteger('allocated_amount');
            $table->timestamps();
        });

        Schema::create('compliance_disclosures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->constrained();
            $table->string('version', 20);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_disclosures');
        Schema::dropIfExists('investment_vehicle_allocations');
        Schema::dropIfExists('investments');
        Schema::dropIfExists('investment_plans');
        Schema::dropIfExists('investors');
    }
};
