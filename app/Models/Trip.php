<?php

namespace App\Models;

use App\Enums\TripStatusEnum;
use App\Exceptions\TripOverlapException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Validation\ValidationException;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'company_id',
        'trip_number',
        'origin',
        'destination',
        'schedule_start',
        'schedule_end',
        'actual_start',
        'actual_end',
        'status',
    ];

    protected $casts = [
        'status' => TripStatusEnum::class,
        'schedule_start' => 'datetime',
        'schedule_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Validate that there are no overlapping trips for the same driver or vehicle
     *
     * @throws TripOverlapException
     */
    public function validateNoOverlappingTrips(): void
    {
        if (!$this->schedule_start || !$this->schedule_end)
            return;

        $query = static::where('id', '!=', $this->id)
            ->where(function ($q) {
                $q->where('driver_id', $this->driver_id)
                  ->orWhere('vehicle_id', $this->vehicle_id);
            })
            ->where('status', '!=', TripStatusEnum::CANCELLED->value)
            ->where(function ($q) {
                $q->whereBetween('schedule_start', [$this->schedule_start, $this->schedule_end])
                  ->orWhereBetween('schedule_end', [$this->schedule_start, $this->schedule_end])
                  ->orWhere(function ($subQ) {
                      $subQ->where('schedule_start', '<=', $this->schedule_start)
                           ->where('schedule_end', '>=', $this->schedule_end);
                  });
            });

        $overlappingTrips = $query->get();

        if ($overlappingTrips->isNotEmpty()) {
            $conflicts = $overlappingTrips->map(function ($trip) {
                $conflictType = [];
                if ($trip->driver_id === $this->driver_id) {
                    $conflictType[] = 'driver';
                }
                if ($trip->vehicle_id === $this->vehicle_id) {
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
     * Check if a specific time range overlaps with any existing trips
     *
     * @param Carbon $start
     * @param Carbon $end
     * @param int|null $driverId
     * @param int|null $vehicleId
     * @param int|null $excludeTripId
     * @return bool
     */
    public static function hasOverlappingTrips(
        Carbon $start,
        Carbon $end,
        ?int $driverId = null,
        ?int $vehicleId = null,
        ?int $excludeTripId = null
    ): bool {
        $query = static::where('id', '!=', $excludeTripId ?? 0)
            ->where('status', '!=', TripStatusEnum::CANCELLED->value)
            ->where(function ($q) use ($driverId, $vehicleId) {
                if ($driverId) {
                    $q->where('driver_id', $driverId);
                }
                if ($vehicleId) {
                    $q->orWhere('vehicle_id', $vehicleId);
                }
            })
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('schedule_start', [$start, $end])
                  ->orWhereBetween('schedule_end', [$start, $end])
                  ->orWhere(function ($subQ) use ($start, $end) {
                      $subQ->where('schedule_start', '<=', $start)
                           ->where('schedule_end', '>=', $end);
                  });
            });

        return $query->exists();
    }

    /**
     * Get overlapping trips for a specific time range
     *
     * @param Carbon $start
     * @param Carbon $end
     * @param int|null $driverId
     * @param int|null $vehicleId
     * @param int|null $excludeTripId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getOverlappingTrips(
        Carbon $start,
        Carbon $end,
        ?int $driverId = null,
        ?int $vehicleId = null,
        ?int $excludeTripId = null
    ) {
        $query = static::where('id', '!=', $excludeTripId ?? 0)
            ->where('status', '!=', TripStatusEnum::CANCELLED->value)
            ->where(function ($q) use ($driverId, $vehicleId) {
                if ($driverId) {
                    $q->where('driver_id', $driverId);
                }
                if ($vehicleId) {
                    $q->orWhere('vehicle_id', $vehicleId);
                }
            })
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('schedule_start', [$start, $end])
                  ->orWhereBetween('schedule_end', [$start, $end])
                  ->orWhere(function ($subQ) use ($start, $end) {
                      $subQ->where('schedule_start', '<=', $start)
                           ->where('schedule_end', '>=', $end);
                  });
            })
            ->with(['driver', 'vehicle']);

        return $query->get();
    }
}


