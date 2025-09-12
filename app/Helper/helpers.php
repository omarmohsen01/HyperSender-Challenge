<?php

use Carbon\Carbon;

if (!function_exists('formatDuration')) {
    /**
     * Format duration between two Carbon dates as hours and minutes
     *
     * @param Carbon|null $start
     * @param Carbon|null $end
     * @return string
     */
    function formatDuration(?Carbon $start, ?Carbon $end): string
    {
        if (!$start || !$end) {
            return '-';
        }

        $minutes = $start->diffInMinutes($end, false);

        if ($minutes <= 0) {
            return '0m';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return ($hours ? ($hours . 'h ') : '') . ($mins ? ($mins . 'm') : '0m');
    }
}
