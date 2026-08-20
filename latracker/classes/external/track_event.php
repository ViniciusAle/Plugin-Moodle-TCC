<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_latracker\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use context_course;

defined('MOODLE_INTERNAL') || die();

/**
 * Receives a single tracking event from the student-facing AMD module.
 *
 * Handles four event types coming from amd/src/tracker.js:
 * - pageview  : time spent on a page (also feeds the accumulated total).
 * - activitystart / activitysubmit : open->submit timer for an activity.
 * - copy / paste : keyboard shortcut usage during activity resolution.
 *
 * sesskey validation and login are enforced by the Moodle AJAX/external
 * function dispatcher itself (loginrequired => true in db/services.php,
 * plus the mandatory sesskey check performed by lib/ajax/service.php for
 * every ajax => true function). require_capability() below additionally
 * makes sure the acting user is allowed to be tracked in this course.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class track_event extends external_api {

    /** @var string[] Allowed event types. */
    const EVENT_TYPES = ['pageview', 'activitystart', 'activitysubmit', 'copy', 'paste'];

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'  => new external_value(PARAM_INT, 'Course id'),
            'cmid'      => new external_value(PARAM_INT, 'Course module id, 0 if not applicable', VALUE_DEFAULT, 0),
            'eventtype' => new external_value(PARAM_ALPHA, 'One of: pageview, activitystart, activitysubmit, copy, paste'),
            'pageurl'   => new external_value(PARAM_URL, 'URL of the page where the event happened', VALUE_DEFAULT, ''),
            'duration'  => new external_value(PARAM_INT, 'Duration in seconds, when applicable', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $courseid, int $cmid, string $eventtype, string $pageurl, int $duration): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'  => $courseid,
            'cmid'      => $cmid,
            'eventtype' => $eventtype,
            'pageurl'   => $pageurl,
            'duration'  => $duration,
        ]);

        if (!in_array($params['eventtype'], self::EVENT_TYPES, true)) {
            throw new \invalid_parameter_exception('Unknown event type');
        }

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/latracker:track', $context);

        global $USER, $DB;
        $now = time();
        $cmid = $params['cmid'] ?: null;

        switch ($params['eventtype']) {
            case 'pageview':
                $DB->insert_record('local_latracker_pageview', (object) [
                    'userid'      => $USER->id,
                    'courseid'    => $params['courseid'],
                    'cmid'        => $cmid,
                    'pageurl'     => \core_text::substr($params['pageurl'], 0, 255),
                    'duration'    => max(0, $params['duration']),
                    'timecreated' => $now,
                ]);
                self::accumulate_session_time($params['courseid'], max(0, $params['duration']));
                break;

            case 'activitystart':
                if ($cmid) {
                    $DB->insert_record('local_latracker_activitytime', (object) [
                        'userid'        => $USER->id,
                        'courseid'      => $params['courseid'],
                        'cmid'          => $cmid,
                        'timestarted'   => $now,
                        'timesubmitted' => null,
                        'duration'      => null,
                    ]);
                }
                break;

            case 'activitysubmit':
                if ($cmid) {
                    $record = $DB->get_record_sql(
                        'SELECT * FROM {local_latracker_activitytime}
                          WHERE userid = :userid AND cmid = :cmid AND timesubmitted IS NULL
                       ORDER BY timestarted DESC',
                        ['userid' => $USER->id, 'cmid' => $cmid],
                        IGNORE_MULTIPLE
                    );
                    if ($record) {
                        $record->timesubmitted = $now;
                        $record->duration = max(0, $now - $record->timestarted);
                        $DB->update_record('local_latracker_activitytime', $record);
                    }
                }
                break;

            case 'copy':
            case 'paste':
                $DB->insert_record('local_latracker_event', (object) [
                    'userid'      => $USER->id,
                    'courseid'    => $params['courseid'],
                    'cmid'        => $cmid,
                    'eventtype'   => $params['eventtype'],
                    'pageurl'     => \core_text::substr($params['pageurl'], 0, 255),
                    'timecreated' => $now,
                ]);
                break;
        }

        return ['success' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the event was stored'),
        ]);
    }

    /**
     * Adds seconds to the accumulated total time for a user in a course.
     */
    private static function accumulate_session_time(int $courseid, int $seconds): void {
        global $USER, $DB;

        if ($seconds <= 0) {
            return;
        }

        $existing = $DB->get_record('local_latracker_sessiontime', [
            'userid'   => $USER->id,
            'courseid' => $courseid,
        ]);

        if ($existing) {
            $existing->totaltime += $seconds;
            $existing->timemodified = time();
            $DB->update_record('local_latracker_sessiontime', $existing);
        } else {
            $DB->insert_record('local_latracker_sessiontime', (object) [
                'userid'       => $USER->id,
                'courseid'     => $courseid,
                'totaltime'    => $seconds,
                'timemodified' => time(),
            ]);
        }
    }
}
