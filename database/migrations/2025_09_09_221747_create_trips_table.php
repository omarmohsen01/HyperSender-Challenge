<?php

use App\Enums\TripStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('driver_id')->constrained('drivers');
            $table->foreignId('company_id')->constrained('companies');
            $table->unsignedInteger('trip_number');
            $table->string('origin');
            $table->string('destination');
            $table->datetime('schedule_start');
            $table->datetime('schedule_end');
            $table->dateTime('actual_start');
            $table->dateTime('actual_end');
            $table->enum('status', TripStatusEnum::values());
            $table->timestamps();

            // INDEXES
            $table->index(['company_id']);
            $table->index(['driver_id']);
            $table->index(['vehicle_id']);
            $table->index(['status']);
            $table->index(['schedule_start']);
            $table->index(['schedule_end']);

            // UNIQUE constraints for overlap prevention per driver/vehicle per schedule window
            $table->unique(['driver_id', 'schedule_start', 'schedule_end'], 'unique_driver_schedule_window');
            $table->unique(['vehicle_id', 'schedule_start', 'schedule_end'], 'unique_vehicle_schedule_window');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
