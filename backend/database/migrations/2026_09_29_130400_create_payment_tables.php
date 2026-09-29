<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Financial records are append-only history: no soft deletes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->string('gateway', 30);
            $table->string('gateway_ref')->nullable();
            $table->unsignedBigInteger('amount');
            $table->char('currency_code', 3);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
            $table->unique(['gateway', 'gateway_ref']); // webhook idempotency
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('type', 20);
            $table->bigInteger('amount'); // signed: credit > 0, debit < 0
            $table->bigInteger('balance_after');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('held')->index();
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('payments');
    }
};
