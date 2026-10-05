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
 * "Rage clicks": bursts of several clicks in quick succession and close
 * together on screen - a well known UX signal that the student is
 * confused, stuck, or clicking something that isn't responding. Grouped
 * per activity so a teacher can see which ones are causing friction.
 *
 * The clustering is done in plain PHP (not SQL window functions), since
 * course-scale click volumes are small enough for this to be simple and
 * fast, and it keeps the query portable to older MySQL/MariaDB versions
 * commonly found on shared hosting.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rage_click_insight implements insight {

    /** @var int Max milliseconds between two clicks for them to belong to the same burst. */
    const RAGE_WINDOW_MS = 900;

    /** @var int Max pixel distance between two clicks for them to belong to the same burst. */
    const RAGE_RADIUS_PX = 60;

    /** @var int Minimum clicks in a burst to count as a "rage click". */
    const RAGE_MIN_CLICKS = 3;

    /** @var int Hard cap on rows fetched, to bound worst-case query cost. */
    const MAX_ROWS = 20000;

    public function get_id(): string {
        return 'rageclick';
    }

    public function get_title(): string {
        return get_string('insight_rageclick_title', 'local_latracker');
    }

    public function get_description(): string {
        return get_string('insight_rageclick_desc', 'local_latracker');
    }

    public function build_chart(int $courseid): \core\chart_base {
        global $DB;

        $sql = "SELECT id, userid, cmid, clientx, clienty, timecreatedms
                  FROM {local_latracker_click}
                 WHERE courseid = :courseid AND cmid IS NOT NULL
              ORDER BY userid ASC, cmid ASC, timecreatedms ASC";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid], 0, self::MAX_ROWS);

        $burstsbycm = $this->count_bursts_per_activity($rows);

        $modinfo = get_fast_modinfo($courseid);
        $labels = [];
        $counts = [];
        foreach ($burstsbycm as $cmid => $count) {
            $cm = $modinfo->cms[$cmid] ?? null;
            $labels[] = $cm ? $cm->name : "cm{$cmid}";
            $counts[] = $count;
        }

        $chart = new \core\chart_bar();
        // Horizontal bars keep activity names fully readable instead of
        // overlapping on a vertical x-axis (same reasoning as
        // assignment_behavior_insight).
        $chart->set_horizontal(true);
        $chart->add_series(new \core\chart_series(get_string('rageburstcount', 'local_latracker'), $counts));
        $chart->set_labels($labels);

        return $chart;
    }

    /**
     * Walks the click rows (already ordered by userid, cmid, time) and
     * counts one "burst" every time a run of consecutive close-in-time,
     * close-in-space clicks by the same user on the same activity first
     * reaches RAGE_MIN_CLICKS - a longer run is still counted only once.
     *
     * @param iterable $rows
     * @return array<int, int> cmid => burst count
     */
    private function count_bursts_per_activity(iterable $rows): array {
        $burstsbycm = [];
        $runlength = 1;
        $prev = null;

        foreach ($rows as $row) {
            if ($prev && $prev->userid === $row->userid && $prev->cmid === $row->cmid
                    && $this->is_same_burst($prev, $row)) {
                $runlength++;
            } else {
                $runlength = 1;
            }

            if ($runlength === self::RAGE_MIN_CLICKS) {
                $burstsbycm[$row->cmid] = ($burstsbycm[$row->cmid] ?? 0) + 1;
            }

            $prev = $row;
        }

        return $burstsbycm;
    }

    private function is_same_burst(\stdClass $prev, \stdClass $current): bool {
        $deltams = $current->timecreatedms - $prev->timecreatedms;
        if ($deltams < 0 || $deltams > self::RAGE_WINDOW_MS) {
            return false;
        }

        $dx = (int) $current->clientx - (int) $prev->clientx;
        $dy = (int) $current->clienty - (int) $prev->clienty;
        $distance = sqrt(($dx * $dx) + ($dy * $dy));

        return $distance <= self::RAGE_RADIUS_PX;
    }
}
