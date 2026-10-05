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
 * Click-based engagement: total mouse clicks per student.
 *
 * This is a proxy for *active* interaction, as opposed to the temporal
 * engagement insight (which only measures time-with-tab-open and can be
 * high for a student who is simply idle with the page in the background).
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class click_engagement_insight implements insight {

    public function get_id(): string {
        return 'clickengagement';
    }

    public function get_title(): string {
        return get_string('insight_clickengagement_title', 'local_latracker');
    }

    public function get_description(): string {
        return get_string('insight_clickengagement_desc', 'local_latracker');
    }

    public function build_chart(int $courseid): \core\chart_base {
        global $DB;

        $sql = "SELECT u.id, " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname, COUNT(c.id) AS clicks
                  FROM {local_latracker_click} c
                  JOIN {user} u ON u.id = c.userid
                 WHERE c.courseid = :courseid
              GROUP BY u.id, u.firstname, u.lastname
              ORDER BY clicks DESC";
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid], 0, 25);

        $labels = [];
        $clicks = [];
        foreach ($records as $record) {
            $labels[] = $record->fullname;
            $clicks[] = (int) $record->clicks;
        }

        $chart = new \core\chart_bar();
        $chart->set_horizontal(true);
        $chart->add_series(new \core\chart_series(get_string('clickcount', 'local_latracker'), $clicks));
        $chart->set_labels($labels);

        return $chart;
    }
}
