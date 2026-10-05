<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_latracker\analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Data provider for the interactive "engagement over time" chart.
 *
 * Unlike the other insights (which implement the simple `insight`
 * interface and are rendered with Moodle's server-side Chart API), this
 * one is rendered client-side with one Chart.js line per student and a
 * dropdown to toggle which students are shown - that interactivity needs
 * direct access to the Chart.js instance, which core's chart renderer
 * does not expose. See amd/src/engagementchart.js and the dedicated
 * markup in templates/analytics.mustache.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engagement_timeline {

    /** @var int Hard cap on rows fetched, to bound worst-case query cost. */
    const MAX_ROWS = 50000;

    /**
     * Builds the {labels, students} structure consumed by the AMD module.
     *
     * Y values are the student's *cumulative* minutes on the platform up
     * to and including that day, so each line reads as a growth curve.
     *
     * @param int $courseid
     * @return array{labels: string[], students: array<int, array{id:int,name:string,data:float[]}>, hasdata: bool}
     */
    public static function get_data(int $courseid): array {
        global $DB;

        $sql = "SELECT pv.id, pv.userid, pv.timecreated, pv.duration,
                       " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname
                  FROM {local_latracker_pageview} pv
                  JOIN {user} u ON u.id = pv.userid
                 WHERE pv.courseid = :courseid
              ORDER BY pv.userid ASC, pv.timecreated ASC";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid], 0, self::MAX_ROWS);

        $byuser = [];
        $days = [];

        foreach ($rows as $row) {
            $day = intdiv((int) $row->timecreated, DAYSECS) * DAYSECS;
            $days[$day] = true;

            if (!isset($byuser[$row->userid])) {
                $byuser[$row->userid] = ['name' => $row->fullname, 'perday' => []];
            }
            $byuser[$row->userid]['perday'][$day] = ($byuser[$row->userid]['perday'][$day] ?? 0) + (int) $row->duration;
        }

        ksort($days);
        $days = array_keys($days);

        $labels = array_map(function($timestamp) {
            return userdate($timestamp, get_string('strftimedateshort', 'langconfig'));
        }, $days);

        $students = [];
        foreach ($byuser as $userid => $info) {
            $cumulative = 0;
            $data = [];
            foreach ($days as $day) {
                $cumulative += $info['perday'][$day] ?? 0;
                $data[] = round($cumulative / 60, 1);
            }
            $students[] = ['id' => (int) $userid, 'name' => $info['name'], 'data' => $data];
        }

        usort($students, function($a, $b) {
            return end($b['data']) <=> end($a['data']);
        });

        return [
            'labels'  => $labels,
            'students' => $students,
            'hasdata' => !empty($students),
        ];
    }

    /**
     * JSON-encodes a get_data() result for embedding in a
     * <script type="application/json"> tag, escaping forward slashes so a
     * student name containing the literal text "</script>" cannot break
     * out of the tag.
     */
    public static function to_json(array $data): string {
        return str_replace('/', '\/', json_encode($data));
    }
}
