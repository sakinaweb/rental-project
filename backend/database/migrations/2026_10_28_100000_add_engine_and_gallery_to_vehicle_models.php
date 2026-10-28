<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Engine size (shown on bike cards instead of seats) and extra photos for the vehicle page gallery.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_models', function (Blueprint $table) {
            $table->unsignedSmallInteger('engine_cc')->nullable()->after('fuel_type');
            $table->json('gallery')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_models', function (Blueprint $table) {
            $table->dropColumn(['engine_cc', 'gallery']);
        });
    }
};
