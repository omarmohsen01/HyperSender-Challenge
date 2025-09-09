<?php

namespace Database\Factories;

use App\Enums\TripStatusEnum;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Trip>
 */
class TripFactory extends Factory
{
    protected $model = Trip::class;

    public function definition(): array
    {
        $scheduleStart = $this->faker->dateTimeBetween('-10 days', '+10 days');
        $scheduleEnd = (clone $scheduleStart)->modify('+' . $this->faker->numberBetween(1, 12) . ' hours');

        $actualStart = (clone $scheduleStart)->modify('+' . $this->faker->numberBetween(0, 120) . ' minutes');
        $actualEnd = (clone $actualStart)->modify('+' . $this->faker->numberBetween(30, 720) . ' minutes');

        $status = $this->faker->randomElement(TripStatusEnum::values());

        return [
            'vehicle_id' => Vehicle::factory(),
            'driver_id' => Driver::factory(),
            'company_id' => Company::factory(),
            'trip_number' => $this->faker->unique()->numberBetween(1000, 999999),
            'origin' => $this->faker->city(),
            'destination' => $this->faker->city(),
            'schedule_start' => $scheduleStart,
            'schedule_end' => $scheduleEnd,
            'actual_start' => $actualStart,
            'actual_end' => $actualEnd,
            'status' => $status,
        ];
    }
}


