<?php

namespace App\Rules;

use App\Interfaces\TripOverlapInterface;
use App\Models\Trip;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class NoOverlappingTrips implements ValidationRule
{
    protected $driverId;
    protected $vehicleId;
    protected $excludeTripId;
    protected $scheduleStart;
    protected $scheduleEnd;
    protected TripOverlapInterface $tripOverlapService;

    public function __construct($driverId, $vehicleId, $scheduleStart, $scheduleEnd, $excludeTripId = null, TripOverlapInterface $tripOverlapService)
    {
        $this->driverId = $driverId;
        $this->vehicleId = $vehicleId;
        $this->scheduleStart = $scheduleStart;
        $this->scheduleEnd = $scheduleEnd;
        $this->excludeTripId = $excludeTripId;
        $this->tripOverlapService = $tripOverlapService;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->scheduleStart || !$this->scheduleEnd) {
            return;
        }

        $start = Carbon::parse($this->scheduleStart);
        $end = Carbon::parse($this->scheduleEnd);

        if ($start->gte($end)) {
            $fail('The schedule start time must be before the schedule end time.');
            return;
        }

        $overlappingTrips = $this->tripOverlapService->getOverlappingTrips(
            $start,
            $end,
            $this->driverId,
            $this->vehicleId,
            $this->excludeTripId
        );

        if ($overlappingTrips->isNotEmpty()) {
            $conflicts = $overlappingTrips->map(function ($trip) {
                $conflictType = [];
                if ($trip->driver_id === $this->driverId) {
                    $conflictType[] = 'driver';
                }
                if ($trip->vehicle_id === $this->vehicleId) {
                    $conflictType[] = 'vehicle';
                }
                return "Trip #{$trip->trip_number} ({$trip->driver->name} / {$trip->vehicle->license_plate}) - " .
                       implode(' and ', $conflictType) . " conflict from {$trip->schedule_start->format('M j, Y H:i')} to {$trip->schedule_end->format('M j, Y H:i')}";
            });

            $fail('Trip overlaps with existing trips: ' . $conflicts->join(', '));
        }
    }
}
