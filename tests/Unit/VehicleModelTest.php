<?php

use App\Models\Company;
use App\Models\Vehicle;
use App\Enums\VehicleStatusEnum;
use App\Enums\VehicleTypeEnum;

test('vehicle can be created', function () {
    $company = Company::factory()->create();

    $vehicleData = [
        'license_plate' => 'ABC-123',
        'model' => 'Toyota Camry',
        'year' => 2023,
        'capacity' => 5,
        'company_id' => $company->id,
        'status' => VehicleStatusEnum::AVAILABLE->value,
        'type' => VehicleTypeEnum::CAR->value,
    ];

    $vehicle = Vehicle::create($vehicleData);

    expect($vehicle->license_plate)->toBe('ABC-123');
    $this->assertDatabaseHas('vehicles', $vehicleData);
});

test('vehicle can be updated', function () {
    $company = Company::factory()->create();
    $vehicle = Vehicle::factory()->create(['company_id' => $company->id]);

    $updateData = [
        'license_plate' => 'XYZ-789',
        'model' => 'Honda Accord',
        'year' => 2024,
        'capacity' => 4,
        'company_id' => $company->id,
        'status' => VehicleStatusEnum::IN_USE->value,
        'type' => VehicleTypeEnum::TRUCK->value,
    ];

    $vehicle->update($updateData);

    $this->assertDatabaseHas('vehicles', array_merge(['id' => $vehicle->id], $updateData));
});

test('vehicle can be deleted', function () {
    $company = Company::factory()->create();
    $vehicle = Vehicle::factory()->create(['company_id' => $company->id]);

    $vehicle->delete();

    $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
});

test('vehicle belongs to company', function () {
    $company = Company::factory()->create();
    $vehicle = Vehicle::factory()->create(['company_id' => $company->id]);

    expect($vehicle->company->id)->toBe($company->id);
    expect($vehicle->company->name)->toBe($company->name);
});

test('vehicle has required attributes', function () {
    $company = Company::factory()->create();
    $vehicle = Vehicle::factory()->create(['company_id' => $company->id]);

    expect($vehicle->license_plate)->not->toBeNull();
    expect($vehicle->model)->not->toBeNull();
    expect($vehicle->year)->not->toBeNull();
    expect($vehicle->capacity)->not->toBeNull();
    expect($vehicle->company_id)->not->toBeNull();
    expect($vehicle->status)->not->toBeNull();
    expect($vehicle->type)->not->toBeNull();
    expect($vehicle->created_at)->not->toBeNull();
});

test('vehicle status enum works', function () {
    $company = Company::factory()->create();
    $vehicle = Vehicle::factory()->create([
        'company_id' => $company->id,
        'status' => VehicleStatusEnum::AVAILABLE
    ]);

    expect($vehicle->status)->toBeInstanceOf(VehicleStatusEnum::class);
    expect($vehicle->status)->toBe(VehicleStatusEnum::AVAILABLE);
});

test('vehicle type enum works', function () {
    $company = Company::factory()->create();
    $vehicle = Vehicle::factory()->create([
        'company_id' => $company->id,
        'type' => VehicleTypeEnum::CAR
    ]);

    expect($vehicle->type)->toBeInstanceOf(VehicleTypeEnum::class);
    expect($vehicle->type)->toBe(VehicleTypeEnum::CAR);
});

test('vehicle can have trips', function () {
    $company = Company::factory()->create();
    $vehicle = Vehicle::factory()->create(['company_id' => $company->id]);
    $driver = \App\Models\Driver::factory()->create(['company_id' => $company->id]);
    $trip = \App\Models\Trip::factory()->create([
        'company_id' => $company->id,
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id
    ]);

    expect($vehicle->trips()->exists())->toBeTrue();
    expect($trip->vehicle_id)->toBe($vehicle->id);
});

test('vehicle year validation', function () {
    $company = Company::factory()->create();

    // Test valid year
    $vehicle = Vehicle::factory()->create([
        'company_id' => $company->id,
        'year' => 2023
    ]);

    expect($vehicle->year)->toBe(2023);
});

test('vehicle capacity validation', function () {
    $company = Company::factory()->create();

    // Test valid capacity
    $vehicle = Vehicle::factory()->create([
        'company_id' => $company->id,
        'capacity' => 5
    ]);

    expect($vehicle->capacity)->toBe(5);
});
