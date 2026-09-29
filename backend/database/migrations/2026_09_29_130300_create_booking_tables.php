<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('price');
            $table->string('type', 20);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('type', 10); // fixed | percent
            $table->unsignedBigInteger('value'); // fixed: minor units; percent: basis points (1250 = 12.5%)
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('pickup_location_id')->constrained('locations');
            $table->foreignId('dropoff_location_id')->constrained('locations');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status', 20)->default('draft')->index();
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('deposit_amount')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['vehicle_id', 'start_at', 'end_at']); // availability lookups
        });

        Schema::create('booking_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained();
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedBigInteger('price');
            $table->timestamps();
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20); // pickup | return
            $table->json('photos_json')->nullable();
            $table->unsignedInteger('odometer')->nullable();
            $table->unsignedTinyInteger('fuel_level')->nullable(); // percent
            $table->text('notes')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->text('description');
            $table->unsignedBigInteger('cost')->default(0);
            $table->json('evidence_json')->nullable();
            $table->string('status', 20)->default('reported')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damages');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('booking_addons');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('addons');
    }
};
