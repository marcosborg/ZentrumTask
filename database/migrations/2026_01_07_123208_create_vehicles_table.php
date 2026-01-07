<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate')->unique()->index();
            $table->string('vin')->nullable()->unique()->index();
            $table->string('make')->index();
            $table->string('model')->index();
            $table->string('trim')->nullable();
            $table->unsignedSmallInteger('year')->nullable()->index();
            $table->string('fuel_type')->nullable()->index();
            $table->string('transmission')->nullable();
            $table->string('color')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->unsignedInteger('engine_cc')->nullable();
            $table->unsignedSmallInteger('power_kw')->nullable();
            $table->unsignedInteger('current_odometer')->nullable();
            $table->string('status')->default('available')->index();
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
