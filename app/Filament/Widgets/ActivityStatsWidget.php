<?php

namespace App\Filament\Widgets;

use App\Enums\TripStatusEnum;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ActivityStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = Cache::remember('activity_stats', 300, function () {
            return [
                'active_trips' => Trip::whereIn('status', [
                    TripStatusEnum::SCHEDULED,
                    TripStatusEnum::IN_PROGRESS
                ])->count(),

                'completed_today' => Trip::where('status', TripStatusEnum::COMPLETED)
                    ->whereDate('actual_end', today())
                    ->count(),

                'total_companies' => Company::count(),
                'total_drivers' => Driver::count(),
                'total_vehicles' => Vehicle::count(),
                'total_trips' => Trip::count(),
            ];
        });

        return [
            Stat::make('Active Trips', $stats['active_trips'])
                ->description('Scheduled & In Progress')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Completed Today', $stats['completed_today'])
                ->description('Trips finished today')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),

            Stat::make('Total Companies', $stats['total_companies'])
                ->description('Registered companies')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),

            Stat::make('Total Drivers', $stats['total_drivers'])
                ->description('Active drivers')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('warning'),

            Stat::make('Total Vehicles', $stats['total_vehicles'])
                ->description('Fleet vehicles')
                ->descriptionIcon('heroicon-m-truck')
                ->color('gray'),

            Stat::make('Total Trips', $stats['total_trips'])
                ->description('All time trips')
                ->descriptionIcon('heroicon-m-map')
                ->color('success'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}
