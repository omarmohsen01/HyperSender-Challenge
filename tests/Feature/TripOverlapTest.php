<?php

namespace Tests\Feature;

use App\Enums\TripStatusEnum;
use App\Exceptions\TripOverlapException;
use App\Interfaces\TripOverlapInterface;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripOverlapTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Driver $driver;
    protected Vehicle $vehicle;
    protected TripOverlapInterface $tripOverlapService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->driver = Driver::factory()->create(['company_id' => $this->company->id]);
        $this->vehicle = Vehicle::factory()->create(['company_id' => $this->company->id]);
        $this->tripOverlapService = app(TripOverlapInterface::class);
    }

    public function test_prevents_driver_overlapping_trips()
    {
        // Create first trip
        $firstTrip = Trip::create([
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'origin' => 'City A',
            'destination' => 'City B',
            'schedule_start' => Carbon::now()->addHours(1),
            'schedule_end' => Carbon::now()->addHours(3),
            'status' => TripStatusEnum::SCHEDULED,
        ]);

        // Try to create overlapping trip with same driver
        $this->expectException(TripOverlapException::class);

        Trip::create([
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'origin' => 'City C',
            'destination' => 'City D',
            'schedule_start' => Carbon::now()->addHours(2),
            'schedule_end' => Carbon::now()->addHours(4),
            'status' => TripStatusEnum::SCHEDULED,
        ]);
    }

    public function test_prevents_vehicle_overlapping_trips()
    {
        $anotherDriver = Driver::factory()->create(['company_id' => $this->company->id]);

        // Create first trip
        $firstTrip = Trip::create([
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'origin' => 'City A',
            'destination' => 'City B',
            'schedule_start' => Carbon::now()->addHours(1),
            'schedule_end' => Carbon::now()->addHours(3),
            'status' => TripStatusEnum::SCHEDULED,
        ]);

        $this->expectException(TripOverlapException::class);

        Trip::create([
            'company_id' => $this->company->id,
            'driver_id' => $anotherDriver->id,
            'vehicle_id' => $this->vehicle->id,
            'origin' => 'City C',
            'destination' => 'City D',
            'schedule_start' => Carbon::now()->addHours(2),
            'schedule_end' => Carbon::now()->addHours(4),
            'status' => TripStatusEnum::SCHEDULED,
        ]);
    }

    public function test_allows_non_overlapping_trips()
    {
        // Create first trip
        $firstTrip = Trip::create([
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'origin' => 'City A',
            'destination' => 'City B',
            'schedule_start' => Carbon::now()->addHours(1),
            'schedule_end' => Carbon::now()->addHours(3),
            'status' => TripStatusEnum::SCHEDULED,
        ]);

        // Create non-overlapping trip
        $secondTrip = Trip::create([
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'origin' => 'City C',
            'destination' => 'City D',
            'schedule_start' => Carbon::now()->addHours(4), // No overlap
            'schedule_end' => Carbon::now()->addHours(6),
            'status' => TripStatusEnum::SCHEDULED,
        ]);

        $this->assertDatabaseHas('trips', ['id' => $secondTrip->id]);
    }

    public function test_allows_overlapping_cancelled_trips()
    {
        // Create first trip
        $firstTrip = Trip::create([
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'origin' => 'City A',
            'destination' => 'City B',
            'schedule_start' => Carbon::now()->addHours(1),
            'schedule_end' => Carbon::now()->addHours(3),
            'status' => TripStatusEnum::CANCELLED,
        ]);

        // Create overlapping trip with cancelled trip (should be allowed)
        $secondTrip = Trip::create([
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'origin' => 'City C',
            'destination' => 'City D',
            'schedule_start' => Carbon::now()->addHours(2), // Overlaps with cancelled trip
            'schedule_end' => Carbon::now()->addHours(4),
            'status' => TripStatusEnum::SCHEDULED,
        ]);

        $this->assertDatabaseHas('trips', ['id' => $secondTrip->id]);
    }

    /** @test */
    public function test_allows_updating_trip_without_overlap_validation_on_itself()
    {
        // Create trip
        $trip = Trip::create([
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'origin' => 'City A',
            'destination' => 'City B',
            'schedule_start' => Carbon::now()->addHours(1),
            'schedule_end' => Carbon::now()->addHours(3),
            'status' => TripStatusEnum::SCHEDULED,
        ]);

        // Update the same trip (should not throw overlap exception)
        $trip->update([
            'origin' => 'Updated City A',
            'destination' => 'Updated City B',
        ]);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'origin' => 'Updated City A',
            'destination' => 'Updated City B',
        ]);
    }

    public function test_trip_overlap_service_can_detect_overlaps()
    {
        // Create first trip
        Trip::create([
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'origin' => 'City A',
            'destination' => 'City B',
            'schedule_start' => Carbon::now()->addHours(1),
            'schedule_end' => Carbon::now()->addHours(3),
            'status' => TripStatusEnum::SCHEDULED,
        ]);

        // Check for overlaps
        $hasOverlap = $this->tripOverlapService->hasOverlappingTrips(
            $this->driver->id,
            $this->vehicle->id,
            Carbon::now()->addHours(2),
            Carbon::now()->addHours(4)
        );

        $this->assertTrue($hasOverlap);
    }

    public function test_trip_overlap_service_can_suggest_alternative_slots()
    {
        // Create trip
        Trip::create([
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'origin' => 'City A',
            'destination' => 'City B',
            'schedule_start' => Carbon::now()->addHours(1),
            'schedule_end' => Carbon::now()->addHours(3),
            'status' => TripStatusEnum::SCHEDULED,
        ]);

        // Get alternative slots
        $alternatives = $this->tripOverlapService->suggestAlternativeSlots(
            $this->driver->id,
            $this->vehicle->id,
            Carbon::now()->addHours(2), // Overlapping time
            Carbon::now()->addHours(4),
            3
        );

        $this->assertNotEmpty($alternatives);
        $this->assertLessThanOrEqual(3, $alternatives->count());
    }
}
