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
 * Behaviour on tasks: average time-to-submit vs. copy/paste incidence,
 * grouped by activity (course module).
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignment_behavior_insight implements insight {

    public function get_id(): string {
        return 'behavior';
    }

    public function get_title(): string {
        return get_string('insight_behavior_title', 'local_latracker');
    }

    public function get_description(): string {
        return get_string('insight_behavior_desc', 'local_latracker');
    }

    public function build_chart(int $courseid): \core\chart_base {
        global $DB;

        $timesql = "SELECT cm.id AS cmid, cm.instance,
                            AVG(at.duration) AS avgduration
                       FROM {local_latracker_activitytime} at
                       JOIN {course_modules} cm ON cm.id = at.cmid
                      WHERE at.courseid = :courseid AND at.duration IS NOT NULL
                   GROUP BY cm.id, cm.instance";
        $times = $DB->get_records_sql($timesql, ['courseid' => $courseid]);

        $eventsql = "SELECT cmid, COUNT(*) AS total
                       FROM {local_latracker_event}
                      WHERE courseid = :courseid AND cmid IS NOT NULL
                   GROUP BY cmid";
        $events = $DB->get_records_sql($eventsql, ['courseid' => $courseid]);

        $modinfo = get_fast_modinfo($courseid);

        $labels = [];
        $minutes = [];
        $copypaste = [];

        foreach ($times as $row) {
            $cm = $modinfo->cms[$row->cmid] ?? null;
            $labels[] = $cm ? $cm->name : "cm{$row->cmid}";
            $minutes[] = round($row->avgduration / 60, 1);
            $copypaste[] = isset($events[$row->cmid]) ? (int) $events[$row->cmid]->total : 0;
        }

        $chart = new \core\chart_bar();
        $chart->add_series(new \core\chart_series(get_string('avgminutestosubmit', 'local_latracker'), $minutes));
        $chart->add_series(new \core\chart_series(get_string('copypasteevents', 'local_latracker'), $copypaste));
        $chart->set_labels($labels);

        return $chart;
    }
}
