<?php

namespace App\Filament\Widgets;

use App\Enums\TripStatusEnum;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ActivityStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $now = Carbon::now();
        $cacheKey = 'dashboard_kpis_' . $now->format('Y-m-d-H-i'); // Cache for 1 minute

        $stats = Cache::remember($cacheKey, 60, function () use ($now) {
            $startOfMonth = $now->copy()->startOfMonth();

            // Cache individual expensive queries
            $activeTrips = Cache::remember('active_trips_count', 30, function () {
                return Trip::whereIn('status', [
                    TripStatusEnum::SCHEDULED,
                    TripStatusEnum::IN_PROGRESS
                ])->count();
            });

            $availableDrivers = Cache::remember('available_drivers_count', 60, function () {
                return Driver::whereDoesntHave('trips', function ($query) {
                    $query->whereIn('status', [
                        TripStatusEnum::SCHEDULED,
                        TripStatusEnum::IN_PROGRESS
                    ]);
                })->count();
            });

            $availableVehicles = Cache::remember('available_vehicles_count', 60, function () {
                return Vehicle::whereDoesntHave('trips', function ($query) {
                    $query->whereIn('status', [
                        TripStatusEnum::SCHEDULED,
                        TripStatusEnum::IN_PROGRESS
                    ]);
                })->count();
            });

            $completedThisMonth = Cache::remember('completed_trips_month_' . $now->format('Y-m'), 300, function () use ($startOfMonth, $now) {
                return Trip::where('status', TripStatusEnum::COMPLETED)
                    ->whereBetween('actual_end', [$startOfMonth, $now])
                    ->count();
            });

            $totalDrivers = Cache::remember('total_drivers_count', 3600, function () {
                return Driver::count();
            });

            $totalVehicles = Cache::remember('total_vehicles_count', 3600, function () {
                return Vehicle::count();
            });

            return [
                'active_trips' => $activeTrips,
                'available_drivers' => $availableDrivers,
                'available_vehicles' => $availableVehicles,
                'completed_this_month' => $completedThisMonth,
                'total_drivers' => $totalDrivers,
                'total_vehicles' => $totalVehicles,
            ];
        });

        return [
            Stat::make('Active Trips Right Now', $stats['active_trips'])
                ->description('Currently scheduled or in progress')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($stats['active_trips'] > 0 ? 'success' : 'gray'),

            Stat::make('Available Drivers', $stats['available_drivers'])
                ->description("Out of {$stats['total_drivers']} total drivers")
                ->descriptionIcon('heroicon-m-user-group')
                ->color($stats['available_drivers'] > 0 ? 'info' : 'warning'),

            Stat::make('Available Vehicles', $stats['available_vehicles'])
                ->description("Out of {$stats['total_vehicles']} total vehicles")
                ->descriptionIcon('heroicon-m-truck')
                ->color($stats['available_vehicles'] > 0 ? 'primary' : 'danger'),

            Stat::make('Trips Completed This Month', $stats['completed_this_month'])
                ->description('Completed since ' . Carbon::now()->startOfMonth()->format('M 1'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }
}
