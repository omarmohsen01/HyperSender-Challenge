<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    /**
     * Clear all dashboard-related caches
     */
    public static function clearDashboardCaches(): void
    {
        $cacheKeys = [
            'dashboard_kpis_*',
            'resource_availability_*',
            'active_trips_count',
            'available_drivers_count',
            'available_vehicles_count',
            'busy_drivers_count',
            'busy_vehicles_count',
            'total_drivers_count',
            'total_vehicles_count',
        ];

        foreach ($cacheKeys as $pattern) {
            if (str_contains($pattern, '*')) {
                // For patterns with wildcards, we need to clear by tags if using Redis
                // For now, we'll clear specific keys
                continue;
            }
            Cache::forget($pattern);
        }

        // Clear monthly caches
        $currentMonth = now()->format('Y-m');
        Cache::forget("completed_trips_month_{$currentMonth}");
    }

    /**
     * Clear caches when trip status changes
     */
    public static function clearTripRelatedCaches(): void
    {
        self::clearDashboardCaches();
    }

    /**
     * Clear caches when driver/vehicle data changes
     */
    public static function clearResourceCaches(): void
    {
        Cache::forget('total_drivers_count');
        Cache::forget('total_vehicles_count');
        self::clearDashboardCaches();
    }
}
