<?php

namespace App\Observers;

use App\Interfaces\TripOverlapInterface;
use App\Models\Trip;
use Carbon\Carbon;

class TripObserver
{
    public function creating(Trip $trip){
        // Assign next trip number
        $trip->trip_number=$this->getNextTripNumber($trip);

        // Validate no overlaps for new trips
        $overlapService = app(TripOverlapInterface::class);
        $overlapService->validateNoOverlappingTrips($trip);
    }
    public function updating(Trip $trip)
    {
        // Validate no overlaps when updating, excluding current trip id
        /** @var TripOverlapInterface $overlapService */
        $overlapService = app(TripOverlapInterface::class);
        $overlapService->validateNoOverlappingTrips($trip);
    }
    private function getNextTripNumber($trip): string
    {
        $year = Carbon::now()->format('Y');

        $lastNumber = trip::whereYear('created_at', Carbon::now()->year)->max('trip_number');

        if ($lastNumber) {
            $currentYear = substr($lastNumber, 0, 4);
            $increment = ($currentYear === $year)
                ? (int)substr($lastNumber, 4) + 1
                : 1;
        } else {
            $increment = 1;
        }

        return $year . str_pad($increment, 4, '0', STR_PAD_LEFT);
    }
}
