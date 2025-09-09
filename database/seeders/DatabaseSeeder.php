<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\Trip;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        // Create a few companies with related drivers, vehicles, and trips
        Company::factory()
            ->count(5)
            ->create()
            ->each(function (Company $company) {
                $drivers = Driver::factory()->count(5)->create([
                    'company_id' => $company->id,
                ]);

                $vehicles = Vehicle::factory()->count(5)->create([
                    'company_id' => $company->id,
                ]);

                // Create trips linking company drivers and vehicles
                Trip::factory()->count(15)->make([
                    'company_id' => $company->id,
                ])->each(function (Trip $trip) use ($company, $drivers, $vehicles) {
                    $trip->driver_id = $drivers->random()->id;
                    $trip->vehicle_id = $vehicles->random()->id;
                    $trip->company_id = $company->id;
                    $trip->save();
                });
            });
    }
}
