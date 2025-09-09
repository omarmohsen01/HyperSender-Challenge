<?php

namespace Database\Factories;

use App\Enums\DriverStatusEnum;
use App\Models\Company;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'phone' => $this->faker->e164PhoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'license_number' => strtoupper($this->faker->bothify('DRV-####-####')),
            'status' => $this->faker->randomElement(DriverStatusEnum::values()),
            'company_id' => Company::factory(),
        ];
    }
}


