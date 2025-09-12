<?php

namespace App\Services;

use App\Models\Trip;
use App\Enums\TripStatusEnum;
use App\Exceptions\TripOverlapException;
use App\Interfaces\TripOverlapInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TripOverlapService implements TripOverlapInterface
{
    /**
     * Validate that there are no overlapping trips for the same driver or vehicle
     *
     */
    public function validateNoOverlappingTrips($trip): void
    {
        if (!$trip->schedule_start || !$trip->schedule_end)
            return;

        $query = Trip::where('id', '!=', $trip->id)
            ->where(function ($q) use ($trip) {
                $q->where('driver_id', $trip->driver_id)
                  ->orWhere('vehicle_id', $trip->vehicle_id);
            })
            ->where('status', '!=', TripStatusEnum::CANCELLED->value)
            ->where(function ($q) use ($trip) {
                $q->whereBetween('schedule_start', [$trip->schedule_start, $trip->schedule_end])
                  ->orWhereBetween('schedule_end', [$trip->schedule_start, $trip->schedule_end])
                  ->orWhere(function ($subQ) use ($trip) {
                      $subQ->where('schedule_start', '<=', $trip->schedule_start)
                           ->where('schedule_end', '>=', $trip->schedule_end);
                  });
            });

        $overlappingTrips = $query->get();

        if ($overlappingTrips->isNotEmpty()) {
            $conflicts = $overlappingTrips->map(function ($trip) {
                $conflictType = [];
                if ($trip->driver_id === $trip->driver_id) {
                    $conflictType[] = 'driver';
                }
                if ($trip->vehicle_id === $trip->vehicle_id) {
                    $conflictType[] = 'vehicle';
                }
                return [
                    'trip_number' => $trip->trip_number,
                    'conflict_type' => implode(' and ', $conflictType),
                    'schedule_start' => $trip->schedule_start->format('Y-m-d H:i:s'),
                    'schedule_end' => $trip->schedule_end->format('Y-m-d H:i:s'),
                ];
            });

            $message = 'Trip overlaps with existing trips: ' .
                      $conflicts->map(fn($c) => "Trip #{$c['trip_number']} ({$c['conflict_type']}) from {$c['schedule_start']} to {$c['schedule_end']}")
                               ->join(', ');

            throw new TripOverlapException($message);
        }
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
    ) {
        $query = Trip::where('id', '!=', $excludeTripId ?? 0)
            ->where('status', '!=', TripStatusEnum::CANCELLED->value)
            ->where(function ($q) use ($driverId, $vehicleId) {
                if ($driverId) {
                    $q->where('driver_id', $driverId);
                }
                if ($vehicleId) {
                    $q->orWhere('vehicle_id', $vehicleId);
                }
            })
            ->where(function ($q) use ($scheduleStart, $scheduleEnd) {
                $q->whereBetween('schedule_start', [$scheduleStart, $scheduleEnd])
                  ->orWhereBetween('schedule_end', [$scheduleStart, $scheduleEnd])
                  ->orWhere(function ($subQ) use ($scheduleStart, $scheduleEnd) {
                      $subQ->where('schedule_start', '<=', $scheduleStart)
                           ->where('schedule_end', '>=', $scheduleEnd);
                  });
            })
            ->with(['driver', 'vehicle']);

        return $query->get();
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
        $query = Trip::where('id', '!=', $excludeTripId ?? 0)
            ->where('status', '!=', TripStatusEnum::CANCELLED->value)
            ->where(function ($q) use ($driverId, $vehicleId) {
                if ($driverId) {
                    $q->where('driver_id', $driverId);
                }
                if ($vehicleId) {
                    $q->orWhere('vehicle_id', $vehicleId);
                }
            })
            ->where(function ($q) use ($scheduleStart, $scheduleEnd) {
                $q->whereBetween('schedule_start', [$scheduleStart, $scheduleEnd])
                  ->orWhereBetween('schedule_end', [$scheduleStart, $scheduleEnd])
                  ->orWhere(function ($subQ) use ($scheduleStart, $scheduleEnd) {
                      $subQ->where('schedule_start', '<=', $scheduleStart)
                           ->where('schedule_end', '>=', $scheduleEnd);
                  });
            });

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
        $existingTrips = Trip::where('driver_id', $driverId)
            ->orWhere('vehicle_id', $vehicleId)
            ->where('status', '!=', TripStatusEnum::CANCELLED->value)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('schedule_start', [$startDate, $endDate])
                      ->orWhereBetween('schedule_end', [$startDate, $endDate])
                      ->orWhere(function ($subQuery) use ($startDate, $endDate) {
                          $subQuery->where('schedule_start', '<=', $startDate)
                                   ->where('schedule_end', '>=', $endDate);
                      });
            })
            ->orderBy('schedule_start')
            ->get();

        $availableSlots = collect();
        $currentTime = $startDate->copy();

        foreach ($existingTrips as $trip) {
            if ($currentTime->lt($trip->schedule_start)) {
                $availableSlots->push([
                    'start' => $currentTime->copy(),
                    'end' => $trip->schedule_start->copy(),
                    'duration_minutes' => $currentTime->diffInMinutes($trip->schedule_start)
                ]);
            }
            $currentTime = max($currentTime, $trip->schedule_end);
        }

        // Add final slot if there's time remaining
        if ($currentTime->lt($endDate)) {
            $availableSlots->push([
                'start' => $currentTime->copy(),
                'end' => $endDate->copy(),
                'duration_minutes' => $currentTime->diffInMinutes($endDate)
            ]);
        }

        return $availableSlots->filter(fn($slot) => $slot['duration_minutes'] > 0);
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
            ->map(function ($slot) {
                return [
                    'start' => $slot['start']->format('Y-m-d H:i:s'),
                    'end' => $slot['start']->copy()->addMinutes($slot['duration_minutes'])->format('Y-m-d H:i:s'),
                    'duration_hours' => round($slot['duration_minutes'] / 60, 1),
                ];
            });
    }
}
