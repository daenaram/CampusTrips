<?php
/**
 * Helper functions for itinerary management
 */

/**
 * Checks if two time ranges overlap
 */
function hasConflict($start1, $end1, $start2, $end2)
{
    // If any date is null or placeholder '0000-00-00', we cannot determine a conflict
    if (!$start1 || !$end1 || !$start2 || !$end2)
        return false;
    if ($start1 === '0000-00-00' || $start2 === '0000-00-00')
        return false;

    $s1 = strtotime($start1);
    $e1 = strtotime($end1);
    $s2 = strtotime($start2);
    $e2 = strtotime($end2);

    // Ensure strtotime produced valid timestamps
    if (!$s1 || !$e1 || !$s2 || !$e2)
        return false;

    // Overlap logic: (StartA < EndB) and (EndA > StartB)
    return ($s1 < $e2) && ($e1 > $s2);
}


function getTripConflicts($flights, $hotels, $activities)
{
    $conflicts = [];
    $timeline = [];

   //flight
    foreach ($flights as $f) {
        if (!empty($f['departure_datetime']) && !empty($f['arrival_datetime'])) {
            $timeline[] = [
                'name' => "Flight " . ($f['flight_number'] ?? 'TBD'),
                'start' => $f['departure_datetime'],
                'end' => $f['arrival_datetime']
            ];
        }
    }

    //  Hotels
    foreach ($hotels as $h) {
        if (!empty($h['planned_check_in']) && !empty($h['planned_check_out'])) {
            $timeline[] = [
                'name' => "Hotel: " . $h['name'],
                'start' => $h['planned_check_in'],
                'end' => $h['planned_check_out']
            ];
        }
    }

    // Activities
    foreach ($activities as $a) {
        // Only add if date is not '0000-00-00' or NULL
        if (!empty($a['activity_date']) && $a['activity_date'] !== '0000-00-00') {
            $timeline[] = [
                'name' => "Activity: " . $a['name'],
                'start' => $a['activity_date'] . ' 00:00:00',
                'end' => $a['activity_date'] . ' 23:59:59'
            ];
        }
    }

    // reference
    $count = count($timeline);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            if (hasConflict($timeline[$i]['start'], $timeline[$i]['end'], $timeline[$j]['start'], $timeline[$j]['end'])) {
                $conflicts[] = $timeline[$i]['name'] . " overlaps with " . $timeline[$j]['name'];
            }
        }
    }

    return $conflicts;
}
