<?php

namespace App\Console\Commands;

use App\Enums\TripStatusEnum;
use App\Exceptions\TripOverlapException;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Interfaces\TripOverlapInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TestTripOverlap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trip:test-overlap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the trip overlap prevention system';

    protected TripOverlapInterface $tripOverlapService;
    public function __construct(TripOverlapInterface $tripOverlapService){
        parent::__construct();
        $this->tripOverlapService = $tripOverlapService;
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Trip Overlap Prevention System...');
        $this->newLine();

        // Create test data
        $company = Company::factory()->create();
        $driver = Driver::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id]);

        $this->info('Created test data:');
        $this->line("- Company: {$company->name}");
        $this->line("- Driver: {$driver->name}");
        $this->line("- Vehicle: {$vehicle->license_plate}");
        $this->newLine();

        // Test 1: Create first trip
        $this->info('Test 1: Creating first trip...');
        try {
            $trip1 = Trip::create([
                'company_id' => $company->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'origin' => 'City A',
                'destination' => 'City B',
                'schedule_start' => Carbon::now()->addHours(1),
                'schedule_end' => Carbon::now()->addHours(3),
                'status' => TripStatusEnum::SCHEDULED,
            ]);
            $this->info("✅ First trip created successfully: #{$trip1->trip_number}");
        } catch (\Exception $e) {
            $this->error("❌ Failed to create first trip: {$e->getMessage()}");
            return;
        }

        // Test 2: Try to create overlapping trip
        $this->info('Test 2: Attempting to create overlapping trip...');
        try {
            $trip2 = Trip::create([
                'company_id' => $company->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'origin' => 'City C',
                'destination' => 'City D',
                'schedule_start' => Carbon::now()->addHours(2), // Overlaps
                'schedule_end' => Carbon::now()->addHours(4),
                'status' => TripStatusEnum::SCHEDULED,
            ]);
            $this->error("❌ Overlap validation failed - trip was created when it shouldn't have been");
        } catch (TripOverlapException $e) {
            $this->info("✅ Overlap correctly prevented: {$e->getMessage()}");
        } catch (\Exception $e) {
            $this->error("❌ Unexpected error: {$e->getMessage()}");
        }

        // Test 3: Create non-overlapping trip
        $this->info('Test 3: Creating non-overlapping trip...');
        try {
            $trip3 = Trip::create([
                'company_id' => $company->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'origin' => 'City E',
                'destination' => 'City F',
                'schedule_start' => Carbon::now()->addHours(4), // No overlap
                'schedule_end' => Carbon::now()->addHours(6),
                'status' => TripStatusEnum::SCHEDULED,
            ]);
            $this->info("✅ Non-overlapping trip created successfully: #{$trip3->trip_number}");
        } catch (\Exception $e) {
            $this->error("❌ Failed to create non-overlapping trip: {$e->getMessage()}");
        }

        // Test 4: Test service methods
        $this->info('Test 4: Testing service methods...');

        $hasOverlap = $this->tripOverlapService->hasOverlappingTrips(
            $driver->id,
            $vehicle->id,
            Carbon::now()->addHours(2),
            Carbon::now()->addHours(4)
        );
        $this->info($hasOverlap ? "✅ Service correctly detected overlap" : "❌ Service failed to detect overlap");

        $alternatives = $this->tripOverlapService->suggestAlternativeSlots(
            $driver->id,
            $vehicle->id,
            Carbon::now()->addHours(2),
            Carbon::now()->addHours(4),
            3
        );
        $this->info("✅ Found {$alternatives->count()} alternative time slots");

        $this->newLine();
        $this->info('🎉 All tests completed!');
    }
}
