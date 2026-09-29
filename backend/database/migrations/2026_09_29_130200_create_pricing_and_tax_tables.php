<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Money columns are integer minor units (MD §4), everywhere.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 30); // e.g. vehicle | model | category | branch
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('unit', 10); // hour | day | week | month
            $table->unsignedBigInteger('base_rate');
            $table->decimal('weekend_multiplier', 5, 2)->default(1);
            $table->decimal('monthly_discount_pct', 5, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained();
            $table->string('name');
            $table->decimal('rate_pct', 6, 3);
            $table->string('applies_to', 30);
            $table->boolean('inclusive')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('pricing_rules');
    }
};
