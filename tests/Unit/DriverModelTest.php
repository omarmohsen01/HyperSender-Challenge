<?php

use App\Models\Company;
use App\Models\Driver;
use App\Enums\DriverStatusEnum;

test('driver can be created', function () {
    $company = Company::factory()->create();

    $driverData = [
        'name' => 'John Doe',
        'license_number' => 'DL123456',
        'phone' => '1234567890',
        'email' => 'john@example.com',
        'company_id' => $company->id,
        'status' => DriverStatusEnum::ACTIVE->value,
    ];

    $driver = Driver::create($driverData);

    expect($driver->name)->toBe('John Doe');
    $this->assertDatabaseHas('drivers', $driverData);
});

test('driver can be updated', function () {
    $company = Company::factory()->create();
    $driver = Driver::factory()->create(['company_id' => $company->id]);

    $updateData = [
        'name' => 'Jane Doe',
        'license_number' => 'DL789012',
        'phone' => '0987654321',
        'email' => 'jane@example.com',
        'company_id' => $company->id,
        'status' => DriverStatusEnum::INACTIVE->value,
    ];

    $driver->update($updateData);

    $this->assertDatabaseHas('drivers', array_merge(['id' => $driver->id], $updateData));
});

test('driver can be deleted', function () {
    $company = Company::factory()->create();
    $driver = Driver::factory()->create(['company_id' => $company->id]);

    $driver->delete();

    $this->assertDatabaseMissing('drivers', ['id' => $driver->id]);
});

test('driver belongs to company', function () {
    $company = Company::factory()->create();
    $driver = Driver::factory()->create(['company_id' => $company->id]);

    expect($driver->company->id)->toBe($company->id);
    expect($driver->company->name)->toBe($company->name);
});

test('driver has required attributes', function () {
    $company = Company::factory()->create();
    $driver = Driver::factory()->create(['company_id' => $company->id]);

    expect($driver->name)->not->toBeNull();
    expect($driver->license_number)->not->toBeNull();
    expect($driver->phone)->not->toBeNull();
    expect($driver->email)->not->toBeNull();
    expect($driver->company_id)->not->toBeNull();
    expect($driver->status)->not->toBeNull();
    expect($driver->created_at)->not->toBeNull();
});

test('driver status enum works', function () {
    $company = Company::factory()->create();
    $driver = Driver::factory()->create([
        'company_id' => $company->id,
        'status' => DriverStatusEnum::ACTIVE
    ]);

    expect($driver->status)->toBeInstanceOf(DriverStatusEnum::class);
    expect($driver->status)->toBe(DriverStatusEnum::ACTIVE);
});

test('driver can have trips', function () {
    $company = Company::factory()->create();
    $driver = Driver::factory()->create(['company_id' => $company->id]);
    $vehicle = \App\Models\Vehicle::factory()->create(['company_id' => $company->id]);
    $trip = \App\Models\Trip::factory()->create([
        'company_id' => $company->id,
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id
    ]);

    expect($driver->trips()->exists())->toBeTrue();
    expect($trip->driver_id)->toBe($driver->id);
});
