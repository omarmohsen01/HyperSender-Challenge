<?php

namespace Database\Factories;

use App\Enums\VehicleStatusEnum;
use App\Enums\VehicleTypeEnum;
use App\Models\Company;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'license_plate' => strtoupper($this->faker->bothify('???-####')),
            'model' => $this->faker->randomElement(['Toyota Hiace', 'Ford Transit', 'Mercedes Sprinter', 'Nissan NV200', 'Isuzu NQR']),
            'year' => (int) $this->faker->numberBetween(2000, (int) date('Y')),
            'capacity' => $this->faker->numberBetween(2, 60),
            'status' => $this->faker->randomElement(VehicleStatusEnum::values()),
            'type' => $this->faker->randomElement(VehicleTypeEnum::values()),
            'company_id' => Company::factory(),
        ];
    }
}


