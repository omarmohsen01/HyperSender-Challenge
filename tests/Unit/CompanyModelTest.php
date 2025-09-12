<?php

use App\Models\Company;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\Trip;

test('company can be created', function () {
    $companyData = [
        'name' => 'Test Company',
        'address' => '123 Test Street',
        'phone' => '1234567890',
        'email' => 'test@company.com',
    ];

    $company = Company::create($companyData);

    expect($company->name)->toBe('Test Company');
    $this->assertDatabaseHas('companies', $companyData);
});

test('company can be updated', function () {
    $company = Company::factory()->create();

    $updateData = [
        'name' => 'Updated Company Name',
        'address' => '456 Updated Street',
        'phone' => '0987654321',
        'email' => 'updated@company.com',
    ];

    $company->update($updateData);

    $this->assertDatabaseHas('companies', array_merge(['id' => $company->id], $updateData));
});

test('company can be deleted without related records', function () {
    $company = Company::factory()->create();

    $company->delete();

    $this->assertDatabaseMissing('companies', ['id' => $company->id]);
});

test('cannot delete company with drivers', function () {
    $company = Company::factory()->create();
    Driver::factory()->create(['company_id' => $company->id]);

    expect(fn() => $company->delete())->toThrow(\Illuminate\Database\QueryException::class);
});

test('cannot delete company with vehicles', function () {
    $company = Company::factory()->create();
    Vehicle::factory()->create(['company_id' => $company->id]);

    expect(fn() => $company->delete())->toThrow(\Illuminate\Database\QueryException::class);
});

test('cannot delete company with trips', function () {
    $company = Company::factory()->create();
    Trip::factory()->create(['company_id' => $company->id]);

    expect(fn() => $company->delete())->toThrow(\Illuminate\Database\QueryException::class);
});

test('company has required attributes', function () {
    $company = Company::factory()->create();

    expect($company->name)->not->toBeNull();
    expect($company->address)->not->toBeNull();
    expect($company->phone)->not->toBeNull();
    expect($company->email)->not->toBeNull();
    expect($company->created_at)->not->toBeNull();
});

test('company can have drivers', function () {
    $company = Company::factory()->create();
    $driver = Driver::factory()->create(['company_id' => $company->id]);

    expect($company->drivers()->exists())->toBeTrue();
    expect($driver->company_id)->toBe($company->id);
});

test('company can have vehicles', function () {
    $company = Company::factory()->create();
    $vehicle = Vehicle::factory()->create(['company_id' => $company->id]);

    expect($company->vehicles()->exists())->toBeTrue();
    expect($vehicle->company_id)->toBe($company->id);
});

test('company can have trips', function () {
    $company = Company::factory()->create();
    $trip = Trip::factory()->create(['company_id' => $company->id]);

    expect($company->trips()->exists())->toBeTrue();
    expect($trip->company_id)->toBe($company->id);
});
