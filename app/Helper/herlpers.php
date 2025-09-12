<?php

if(!function_exist('formatDuration')){
    function formatDuration($start, $end): string{
        if (!$start || !$end) {
            return '0m';
        }

        $minutes = $start->diffInMinutes($end, false);
        if ($minutes <= 0) {
            return '0m';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return ($hours ? "{$hours}h " : '') . ($mins ? "{$mins}m" : '0m');
    }
}
