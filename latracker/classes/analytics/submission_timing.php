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
 * Data provider for the interactive "submission timing" chart.
 *
 * Like engagement_timeline, this is not part of the plain `insight`
 * registry: it needs one Chart.js dataset per activity plus a dropdown to
 * toggle which activities are shown, so it is rendered client-side (see
 * amd/src/submissiontiming.js) instead of through core's server-rendered
 * Chart API. All of a course's local_latracker_submission rows are small
 * enough in volume to send to the browser in one go, so - like the
 * engagement timeline - there is no extra AJAX round trip when the
 * activity filter changes.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_timing {

    /** @var int Hard cap on rows fetched, to bound worst-case query cost. */
    const MAX_ROWS = 20000;

    /** @var string[] Lang string keys for the 5 timing buckets, in bucket-index order. */
    const BUCKET_STRINGS = [
        'submissionbucket_early3',
        'submissionbucket_early1to3',
        'submissionbucket_early6to24h',
        'submissionbucket_lastminute',
        'submissionbucket_late',
    ];

    /**
     * Builds the {buckets, activities} structure consumed by the AMD module.
     *
     * @param int $courseid
     * @return array{buckets: string[], activities: array<int, array{cmid:int,name:string,data:int[]}>, hasdata: bool}
     */
    public static function get_data(int $courseid): array {
        global $DB;

        $rows = $DB->get_records('local_latracker_submission', ['courseid' => $courseid], '', 'id, cmid, secondsbeforedue',
            0, self::MAX_ROWS);

        $byactivity = [];
        foreach ($rows as $row) {
            if (!isset($byactivity[$row->cmid])) {
                $byactivity[$row->cmid] = array_fill(0, count(self::BUCKET_STRINGS), 0);
            }
            $byactivity[$row->cmid][self::bucket_index((int) $row->secondsbeforedue)]++;
        }

        $modinfo = get_fast_modinfo($courseid);
        $activities = [];
        foreach ($byactivity as $cmid => $counts) {
            $cm = $modinfo->cms[$cmid] ?? null;
            $activities[] = [
                'cmid' => (int) $cmid,
                'name' => $cm ? $cm->get_formatted_name() : "cm{$cmid}",
                'data' => $counts,
            ];
        }

        usort($activities, function($a, $b) {
            return array_sum($b['data']) <=> array_sum($a['data']);
        });

        $buckets = array_map(function($stringkey) {
            return get_string($stringkey, 'local_latracker');
        }, self::BUCKET_STRINGS);

        return [
            'buckets'    => $buckets,
            'activities' => $activities,
            'hasdata'    => !empty($activities),
        ];
    }

    private static function bucket_index(int $secondsbeforedue): int {
        if ($secondsbeforedue < 0) {
            return 4; // Late.
        }
        if ($secondsbeforedue <= 6 * HOURSECS) {
            return 3; // Last few hours.
        }
        if ($secondsbeforedue <= DAYSECS) {
            return 2; // 6-24 hours early.
        }
        if ($secondsbeforedue <= 3 * DAYSECS) {
            return 1; // 1-3 days early.
        }
        return 0; // More than 3 days early.
    }

    /**
     * JSON-encodes a get_data() result for embedding in a
     * <script type="application/json"> tag, escaping forward slashes so
     * an activity name containing the literal text "</script>" cannot
     * break out of the tag.
     */
    public static function to_json(array $data): string {
        return str_replace('/', '\/', json_encode($data));
    }
}
