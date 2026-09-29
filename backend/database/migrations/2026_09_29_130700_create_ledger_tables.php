<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ledger: built in v1.0, used from v1.1 (MD §3). Append-only, no soft deletes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 20); // platform | partner | investor
            $table->unsignedBigInteger('owner_id')->nullable(); // null for platform
            $table->char('currency_code', 3);
            $table->bigInteger('balance')->default(0);
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'currency_code']);
        });

        Schema::create('earnings_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('ledger_accounts');
            $table->foreignId('booking_id')->nullable()->constrained();
            $table->string('entry_type', 20); // credit_share | debit_deduction | payout | reversal
            $table->bigInteger('amount'); // signed
            $table->bigInteger('running_balance');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['account_id', 'id']);
        });

        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('ledger_accounts');
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('ledger_accounts');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statements');
        Schema::dropIfExists('payout_batches');
        Schema::dropIfExists('payout_requests');
        Schema::dropIfExists('earnings_ledger');
        Schema::dropIfExists('ledger_accounts');
    }
};
