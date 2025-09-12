# Trip Overlap Prevention System

This document describes the comprehensive business logic implemented to prevent overlapping trips in the HyperSender application.

## Overview

The system prevents overlapping trips by ensuring that:
1. **Drivers** cannot be assigned to multiple trips at the same time
2. **Vehicles** cannot be used for multiple trips simultaneously
3. **Cancelled trips** are excluded from overlap validation
4. **Real-time validation** occurs during trip creation and updates

## Architecture

### 1. Model-Level Validation (`app/Models/Trip.php`)

The `Trip` model includes automatic validation through Eloquent events:

```php
protected static function boot()
{
    parent::boot();

    static::creating(function ($trip) {
        $trip->validateNoOverlappingTrips();
    });

    static::updating(function ($trip) {
        $trip->validateNoOverlappingTrips();
    });
}
```

**Key Methods:**
- `validateNoOverlappingTrips()`: Main validation method that throws `TripOverlapException`
- `hasOverlappingTrips()`: Static method to check for overlaps
- `getOverlappingTrips()`: Static method to retrieve conflicting trips

### 2. Custom Validation Rule (`app/Rules/NoOverlappingTrips.php`)

Provides real-time validation for Filament forms:

```php
$rule = new NoOverlappingTrips(
    $driverId,
    $vehicleId,
    $scheduleStart,
    $scheduleEnd,
    $excludeTripId
);
```

### 3. Service Layer (`app/Services/TripOverlapService.php`) implemented from Interface Layer (`app/Interfaces/TripOverlapInterfaces.php`)

High-level service for overlap management:

- `validateNoOverlaps()`: Validates and throws exceptions
- `getOverlappingTrips()`: Retrieves conflicting trips
- `hasOverlappingTrips()`: Boolean check for overlaps
- `getAvailableTimeSlots()`: Finds free time slots
- `suggestAlternativeSlots()`: Suggests alternative scheduling

### 4. Custom Exception (`app/Exceptions/TripOverlapException.php`)

Specialized exception for overlap conflicts with detailed error messages.

## Database Constraints

### Migration: `create_trips_table.php`

```php
// Performance indexes, Constraints

The original migration already includes unique constraints:
```php
$table->unique(['driver_id', 'schedule_start', 'schedule_end'], 'unique_driver_schedule_window');
$table->unique(['vehicle_id', 'schedule_start', 'schedule_end'], 'unique_vehicle_schedule_window');
```

## Filament Integration

### TripResource Form Validation

The Filament form includes real-time validation:

```php
DateTimePicker::make('schedule_end')
    ->rules([
        function (Get $get) {
            return function (string $attribute, $value, \Closure $fail) use ($get) {
                $rule = new NoOverlappingTrips(
                    $get('driver_id'),
                    $get('vehicle_id'),
                    $get('schedule_start'),
                    $value,
                    $get('id')
                );
                $rule->validate($attribute, $value, $fail);
            };
        }
    ])
```

## Overlap Detection Logic

The system detects overlaps using three conditions:

1. **Start overlap**: New trip starts during existing trip
2. **End overlap**: New trip ends during existing trip  
3. **Complete overlap**: New trip completely encompasses existing trip

```php
->where(function ($q) {
    $q->whereBetween('schedule_start', [$this->schedule_start, $this->schedule_end])
      ->orWhereBetween('schedule_end', [$this->schedule_start, $this->schedule_end])
      ->orWhere(function ($subQ) {
          $subQ->where('schedule_start', '<=', $this->schedule_start)
               ->where('schedule_end', '>=', $this->schedule_end);
      });
})
```

## Testing

Comprehensive test suite in `tests/Feature/TripOverlapTest.php`:

- ✅ Prevents driver overlapping trips
- ✅ Prevents vehicle overlapping trips  
- ✅ Allows non-overlapping trips
- ✅ Allows overlapping cancelled trips
- ✅ Allows updating existing trips
- ✅ Service layer functionality
- ✅ Alternative slot suggestions

## Usage Examples

### Creating a Trip with Validation

```php
try {
    $trip = Trip::create([
        'company_id' => 1,
        'driver_id' => 1,
        'vehicle_id' => 1,
        'origin' => 'City A',
        'destination' => 'City B',
        'schedule_start' => Carbon::now()->addHours(1),
        'schedule_end' => Carbon::now()->addHours(3),
        'status' => TripStatusEnum::SCHEDULED,
    ]);
} catch (TripOverlapException $e) {
    // Handle overlap conflict
    echo $e->getMessage();
}
```

### Checking for Overlaps

```php
$hasOverlap = TripOverlapService::hasOverlappingTrips(
    $driverId,
    $vehicleId,
    $scheduleStart,
    $scheduleEnd
);
```

### Getting Alternative Time Slots

```php
$alternatives = TripOverlapService::suggestAlternativeSlots(
    $driverId,
    $vehicleId,
    $requestedStart,
    $requestedEnd,
    5 // Max suggestions
);
```
