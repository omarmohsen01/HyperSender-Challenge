<?php

namespace App\Interfaces;

use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface TripOverlapInterface
{
    /**
     * Validate that there are no overlapping trips for the same driver or vehicle
     *
     */
    public function validateNoOverlappingTrips(Trip $trip): void;
     /**
     * Get overlapping trips for a specific time range
     *
     */
    public function getOverlappingTrips(Carbon $scheduleStart, Carbon $scheduleEnd, ?int $driverId = null, ?int $vehicleId = null, ?int $excludeTripId = null);
    /**
     * Check if a time range has any overlapping trips
     *
     */
    public function hasOverlappingTrips(int $driverId, int $vehicleId, Carbon $scheduleStart, Carbon $scheduleEnd, ?int $excludeTripId = null): bool;

    /**
     * Get available time slots for a driver/vehicle combination
     *
     */
    public function getAvailableTimeSlots(int $driverId, int $vehicleId, Carbon $startDate, Carbon $endDate): Collection;

    /**
     * Suggest alternative time slots when overlap is detected
     *
     */
    public function suggestAlternativeSlots(int $driverId, int $vehicleId, Carbon $requestedStart, Carbon $requestedEnd, int $maxSuggestions = 5): Collection;
}
