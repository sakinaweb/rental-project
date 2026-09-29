<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 10)->index(); // bike | car
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained();
            $table->string('name');
            $table->foreignId('category_id')->constrained('vehicle_categories');
            $table->unsignedTinyInteger('seats')->nullable();
            $table->string('transmission', 20)->nullable();
            $table->string('fuel_type', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('vehicle_models');
            $table->foreignId('branch_id')->constrained();
            $table->string('owner_type', 20)->default('company'); // company | partner
            $table->unsignedBigInteger('owner_id')->nullable(); // partners.id; FK added in the partners migration
            $table->string('plate_no', 20)->unique();
            $table->string('status', 20)->default('available')->index();
            $table->unsignedInteger('odometer')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('file_path');
            $table->date('expiry_date')->nullable()->index();
            $table->string('status', 20)->default('valid');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_documents');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('vehicle_categories');
    }
};
