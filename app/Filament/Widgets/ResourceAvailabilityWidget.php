<?php

namespace App\Filament\Widgets;

use App\Enums\TripStatusEnum;
use App\Models\Driver;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ResourceAvailabilityWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $now = Carbon::now();
        $cacheKey = 'resource_availability_' . $now->format('Y-m-d-H-i'); // Cache for 1 minute

        $availability = Cache::remember($cacheKey, 60, function () {
            // Cache total counts for longer periods (1 hour)
            $totalDrivers = Cache::remember('total_drivers_count', 3600, function () {
                return Driver::count();
            });

            $totalVehicles = Cache::remember('total_vehicles_count', 3600, function () {
                return Vehicle::count();
            });

            // Cache busy counts for shorter periods (1 minute)
            $busyDrivers = Cache::remember('busy_drivers_count', 60, function () {
                return Driver::whereHas('trips', function ($query) {
                    $query->whereIn('status', [
                        TripStatusEnum::SCHEDULED,
                        TripStatusEnum::IN_PROGRESS
                    ]);
                })->count();
            });

            $busyVehicles = Cache::remember('busy_vehicles_count', 60, function () {
                return Vehicle::whereHas('trips', function ($query) {
                    $query->whereIn('status', [
                        TripStatusEnum::SCHEDULED,
                        TripStatusEnum::IN_PROGRESS
                    ]);
                })->count();
            });

            return [
                'total_drivers' => $totalDrivers,
                'available_drivers' => $totalDrivers - $busyDrivers,
                'busy_drivers' => $busyDrivers,
                'total_vehicles' => $totalVehicles,
                'available_vehicles' => $totalVehicles - $busyVehicles,
                'busy_vehicles' => $busyVehicles,
            ];
        });

        return [
            Stat::make('Driver Availability', $availability['available_drivers'])
                ->description("{$availability['busy_drivers']} busy, {$availability['total_drivers']} total")
                ->descriptionIcon('heroicon-m-user-group')
                ->color($availability['available_drivers'] > 0 ? 'success' : 'danger'),

            Stat::make('Vehicle Availability', $availability['available_vehicles'])
                ->description("{$availability['busy_vehicles']} busy, {$availability['total_vehicles']} total")
                ->descriptionIcon('heroicon-m-truck')
                ->color($availability['available_vehicles'] > 0 ? 'success' : 'danger'),

            Stat::make('Utilization Rate', $this->calculateUtilizationRate($availability))
                ->description('Drivers & vehicles in use')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($this->getUtilizationColor($availability)),
        ];
    }

    private function calculateUtilizationRate(array $availability): string
    {
        $totalResources = $availability['total_drivers'] + $availability['total_vehicles'];
        $busyResources = $availability['busy_drivers'] + $availability['busy_vehicles'];

        if ($totalResources === 0) {
            return '0%';
        }

        $rate = round(($busyResources / $totalResources) * 100, 1);
        return $rate . '%';
    }

    private function getUtilizationColor(array $availability): string
    {
        $totalResources = $availability['total_drivers'] + $availability['total_vehicles'];
        $busyResources = $availability['busy_drivers'] + $availability['busy_vehicles'];

        if ($totalResources === 0) {
            return 'gray';
        }

        $rate = ($busyResources / $totalResources) * 100;

        if ($rate >= 90) return 'danger';
        if ($rate >= 70) return 'warning';
        if ($rate >= 50) return 'info';
        return 'success';
    }
}
