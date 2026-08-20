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
 * Temporal engagement: accumulated platform time per student.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engagement_insight implements insight {

    public function get_id(): string {
        return 'engagement';
    }

    public function get_title(): string {
        return get_string('insight_engagement_title', 'local_latracker');
    }

    public function get_description(): string {
        return get_string('insight_engagement_desc', 'local_latracker');
    }

    public function build_chart(int $courseid): \core\chart_base {
        global $DB;

        $sql = "SELECT u.id, " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname, st.totaltime
                  FROM {local_latracker_sessiontime} st
                  JOIN {user} u ON u.id = st.userid
                 WHERE st.courseid = :courseid
              ORDER BY st.totaltime DESC";
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid], 0, 25);

        $labels = [];
        $minutes = [];
        foreach ($records as $record) {
            $labels[] = $record->fullname;
            $minutes[] = round($record->totaltime / 60, 1);
        }

        $chart = new \core\chart_bar();
        $chart->set_horizontal(true);
        $series = new \core\chart_series(get_string('minutesonplatform', 'local_latracker'), $minutes);
        $chart->add_series($series);
        $chart->set_labels($labels);

        return $chart;
    }
}
