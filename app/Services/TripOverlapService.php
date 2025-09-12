<?php

namespace App\Services;

use App\Models\Trip;
use App\Enums\TripStatusEnum;
use App\Exceptions\TripOverlapException;
use App\Interfaces\TripOverlapInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class TripOverlapService implements TripOverlapInterface
{
    /**
     * Validate that there are no overlapping trips for the same driver or vehicle
     *
     */
    public function validateNoOverlappingTrips(Trip $trip): void
    {
        if (!$trip->schedule_start || !$trip->schedule_end)
            return;

        $overlappingTrips = $this->getOverlappingTrips(
            $trip->schedule_start,
            $trip->schedule_end,
            $trip->driver_id,
            $trip->vehicle_id,
            $trip->id
        );

        if ($overlappingTrips->isNotEmpty())
            $this->throwOverlapException($overlappingTrips, $trip);
    }

    /**
     * Get overlapping trips for a specific time range
     *
     */
    public function getOverlappingTrips(
        Carbon $scheduleStart,
        Carbon $scheduleEnd,
        ?int $driverId = null,
        ?int $vehicleId = null,
        ?int $excludeTripId = null
    ): Collection {
        $query = $this->buildOverlapQuery($scheduleStart, $scheduleEnd, $driverId, $vehicleId, $excludeTripId);

        return $query->with(['driver', 'vehicle'])->get();
    }

    /**
     * Check if a time range has any overlapping trips
     *
     */
    public function hasOverlappingTrips(
        int $driverId,
        int $vehicleId,
        Carbon $scheduleStart,
        Carbon $scheduleEnd,
        ?int $excludeTripId = null
    ): bool {
        $query = $this->buildOverlapQuery($scheduleStart, $scheduleEnd, $driverId, $vehicleId, $excludeTripId);

        return $query->exists();
    }

    /**
     * Get available time slots for a driver/vehicle combination
     *
     */
    public function getAvailableTimeSlots(
        int $driverId,
        int $vehicleId,
        Carbon $startDate,
        Carbon $endDate
    ): Collection {
        $existingTrips = $this->getTripsInDateRange($driverId, $vehicleId, $startDate, $endDate);

        return $this->calculateAvailableSlots($existingTrips, $startDate, $endDate);
    }

    /**
     * Suggest alternative time slots when overlap is detected
     *
     */
    public function suggestAlternativeSlots(
        int $driverId,
        int $vehicleId,
        Carbon $requestedStart,
        Carbon $requestedEnd,
        int $maxSuggestions = 5
    ): Collection {
        $duration = $requestedStart->diffInMinutes($requestedEnd);
        $searchStart = $requestedStart->copy()->subDays(7);
        $searchEnd = $requestedEnd->copy()->addDays(7);

        $availableSlots = $this->getAvailableTimeSlots(
            $driverId,
            $vehicleId,
            $searchStart,
            $searchEnd
        );

        return $availableSlots
            ->filter(fn($slot) => $slot['duration_minutes'] >= $duration)
            ->take($maxSuggestions)
            ->map(function ($slot) use ($duration) {
                return [
                    'start' => $slot['start']->format('Y-m-d H:i:s'),
                    'end' => $slot['start']->copy()->addMinutes($duration)->format('Y-m-d H:i:s'),
                    'duration_hours' => round($duration / 60, 1),
                    'available_duration_hours' => round($slot['duration_minutes'] / 60, 1),
                ];
            });
    }

    /**
     * Check for resource availability at a specific time
     *
     */
    public function checkResourceAvailability(int $driverId, int $vehicleId, Carbon $datetime): array
    {
        $driverBusy = Trip::where('driver_id', $driverId)
            ->where('status', '!=', TripStatusEnum::CANCELLED)
            ->where('schedule_start', '<=', $datetime)
            ->where('schedule_end', '>', $datetime)
            ->exists();

        $vehicleBusy = Trip::where('vehicle_id', $vehicleId)
            ->where('status', '!=', TripStatusEnum::CANCELLED)
            ->where('schedule_start', '<=', $datetime)
            ->where('schedule_end', '>', $datetime)
            ->exists();

        return [
            'driver_available' => !$driverBusy,
            'vehicle_available' => !$vehicleBusy,
            'both_available' => !$driverBusy && !$vehicleBusy,
        ];
    }

    /**
     * Get the next available time slot for both driver and vehicle
     *
     */
    public function getNextAvailableSlot(
        int $driverId,
        int $vehicleId,
        Carbon $fromDateTime,
        int $durationMinutes
    ): ?array {
        $searchEnd = $fromDateTime->copy()->addDays(30); // Search within 30 days

        $availableSlots = $this->getAvailableTimeSlots(
            $driverId,
            $vehicleId,
            $fromDateTime,
            $searchEnd
        );

        $suitableSlot = $availableSlots
            ->filter(fn($slot) => $slot['duration_minutes'] >= $durationMinutes)
            ->first();

        if (!$suitableSlot) {
            return null;
        }

        return [
            'start' => $suitableSlot['start']->format('Y-m-d H:i:s'),
            'end' => $suitableSlot['start']->copy()->addMinutes($durationMinutes)->format('Y-m-d H:i:s'),
            'duration_hours' => round($durationMinutes / 60, 1),
        ];
    }

    
    /**
     * Build the base query for finding overlapping trips
     *
     */
    private function buildOverlapQuery(
        Carbon $scheduleStart,
        Carbon $scheduleEnd,
        ?int $driverId = null,
        ?int $vehicleId = null,
        ?int $excludeTripId = null
    ): Builder {
        return Trip::query()
            ->when($excludeTripId, fn($q) => $q->where('id', '!=', $excludeTripId))
            ->where('status', '!=', TripStatusEnum::CANCELLED)
            ->when($driverId || $vehicleId, function ($query) use ($driverId, $vehicleId) {
                $query->where(function ($q) use ($driverId, $vehicleId) {
                    if ($driverId) {
                        $q->where('driver_id', $driverId);
                    }
                    if ($vehicleId) {
                        $q->when($driverId, fn($subQ) => $subQ->orWhere('vehicle_id', $vehicleId))
                          ->when(!$driverId, fn($subQ) => $subQ->where('vehicle_id', $vehicleId));
                    }
                });
            })
            ->where(function ($query) use ($scheduleStart, $scheduleEnd) {
                $query
                    // Trip starts within the range
                    ->whereBetween('schedule_start', [$scheduleStart, $scheduleEnd])
                    // Trip ends within the range
                    ->orWhereBetween('schedule_end', [$scheduleStart, $scheduleEnd])
                    // Trip completely encompasses the range
                    ->orWhere(function ($subQuery) use ($scheduleStart, $scheduleEnd) {
                        $subQuery->where('schedule_start', '<=', $scheduleStart)
                                 ->where('schedule_end', '>=', $scheduleEnd);
                    })
                    // Range completely encompasses the trip
                    ->orWhere(function ($subQuery) use ($scheduleStart, $scheduleEnd) {
                        $subQuery->where('schedule_start', '>=', $scheduleStart)
                                 ->where('schedule_end', '<=', $scheduleEnd);
                    });
            });
    }

    /**
     * Get trips within a specific date range
     *
     */
    private function getTripsInDateRange(
        int $driverId,
        int $vehicleId,
        Carbon $startDate,
        Carbon $endDate
    ): Collection {
        return Trip::query()
            ->where(function ($query) use ($driverId, $vehicleId) {
                $query->where('driver_id', $driverId)
                      ->orWhere('vehicle_id', $vehicleId);
            })
            ->where('status', '!=', TripStatusEnum::CANCELLED)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('schedule_start', [$startDate, $endDate])
                      ->orWhereBetween('schedule_end', [$startDate, $endDate])
                      ->orWhere(function ($subQuery) use ($startDate, $endDate) {
                          $subQuery->where('schedule_start', '<=', $startDate)
                                   ->where('schedule_end', '>=', $endDate);
                      });
            })
            ->orderBy('schedule_start')
            ->get(['id', 'schedule_start', 'schedule_end', 'driver_id', 'vehicle_id']);
    }

    /**
     * Calculate available time slots from existing trips
     *
     */
    private function calculateAvailableSlots(
        Collection $existingTrips,
        Carbon $startDate,
        Carbon $endDate
    ): Collection {
        $availableSlots = collect();
        $currentTime = $startDate->copy();

        // Sort trips by start time to ensure proper gap calculation
        $sortedTrips = $existingTrips->sortBy('schedule_start');

        foreach ($sortedTrips as $trip) {
            // If there's a gap between current time and trip start
            if ($currentTime->lt($trip->schedule_start)) {
                $durationMinutes = $currentTime->diffInMinutes($trip->schedule_start);
                if ($durationMinutes > 0) {
                    $availableSlots->push([
                        'start' => $currentTime->copy(),
                        'end' => $trip->schedule_start->copy(),
                        'duration_minutes' => $durationMinutes
                    ]);
                }
            }

            // Move current time to the end of this trip (or keep it if it's already later)
            $currentTime = $currentTime->max($trip->schedule_end);
        }

        // Add final slot if there's time remaining after the last trip
        if ($currentTime->lt($endDate)) {
            $durationMinutes = $currentTime->diffInMinutes($endDate);
            if ($durationMinutes > 0) {
                $availableSlots->push([
                    'start' => $currentTime->copy(),
                    'end' => $endDate->copy(),
                    'duration_minutes' => $durationMinutes
                ]);
            }
        }

        return $availableSlots->filter(fn($slot) => $slot['duration_minutes'] > 0);
    }

    /**
     * Throw overlap exception with detailed conflict information
     *
     */
    private function throwOverlapException(Collection $overlappingTrips, Trip $currentTrip): void
    {
        $conflicts = $overlappingTrips->map(function ($overlappingTrip) use ($currentTrip) {
            $conflictTypes = [];

            if ($overlappingTrip->driver_id === $currentTrip->driver_id) {
                $conflictTypes[] = 'driver';
            }
            if ($overlappingTrip->vehicle_id === $currentTrip->vehicle_id) {
                $conflictTypes[] = 'vehicle';
            }

            return [
                'trip_number' => $overlappingTrip->trip_number,
                'conflict_type' => implode(' and ', $conflictTypes),
                'schedule_start' => $overlappingTrip->schedule_start->format('Y-m-d H:i:s'),
                'schedule_end' => $overlappingTrip->schedule_end->format('Y-m-d H:i:s'),
            ];
        });

        $message = 'Trip overlaps with existing trips: ' .
                   $conflicts->map(fn($c) => "Trip #{$c['trip_number']} ({$c['conflict_type']}) from {$c['schedule_start']} to {$c['schedule_end']}")
                            ->join(', ');

        throw new TripOverlapException($message);
    }

}
