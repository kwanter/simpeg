<?php

namespace App\Services;

use App\Models\HariLibur;

class WorkdayService
{
    public static function countWorkdays($startDate, $endDate)
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $workdays = 0;

        $nonWorkdays = HariLibur::getHariLiburByDateRange($startDate, $endDate);

        $current = clone $start;
        while ($current <= $end) {
            $currentDateStr = $current->format('Y-m-d');

            if (! in_array($currentDateStr, $nonWorkdays)) {
                $workdays++;
            }

            $current->modify('+1 day');
        }

        return $workdays;
    }
}
