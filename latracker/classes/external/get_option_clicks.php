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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use context_course;

defined('MOODLE_INTERNAL') || die();

/**
 * Returns which answer/choice option each student clicked on a given
 * quiz or choice activity, for the "selected options" insight on
 * analytics.php. The teacher picks the activity from a dropdown (which
 * triggers this call); the returned data for every student is then sent
 * to the browser once, and the student filter dropdown - plus the
 * per-student click sequence view - recompute purely client-side (see
 * amd/src/optionclicks.js), no further AJAX round trips needed.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_option_clicks extends external_api {

    /** @var int Hard cap on rows fetched, to bound worst-case query cost. */
    const MAX_ROWS = 20000;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'cmid'     => new external_value(PARAM_INT, 'Course module id of the quiz/choice activity'),
        ]);
    }

    public static function execute(int $courseid, int $cmid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid'     => $cmid,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/latracker:viewanalytics', $context);

        global $DB;

        $sql = "SELECT c.id, c.userid, " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname,
                       c.optionlabel, c.questioncontext, c.timecreatedms
                  FROM {local_latracker_click} c
                  JOIN {user} u ON u.id = c.userid
                 WHERE c.courseid = :courseid AND c.cmid = :cmid AND c.optionlabel IS NOT NULL
              ORDER BY c.id ASC";
        $rows = $DB->get_records_sql($sql, ['courseid' => $params['courseid'], 'cmid' => $params['cmid']], 0, self::MAX_ROWS);

        $students = [];
        $options = [];
        $optionkeys = [];
        $events = [];

        foreach ($rows as $row) {
            if (!isset($students[$row->userid])) {
                $students[$row->userid] = ['id' => (int) $row->userid, 'name' => $row->fullname];
            }

            $question = $row->questioncontext ?? '';
            $key = $question . '|' . $row->optionlabel;

            $optionindex = array_search($key, $optionkeys, true);
            if ($optionindex === false) {
                $optionkeys[] = $key;
                $options[] = ['label' => $row->optionlabel, 'question' => $question];
                $optionindex = array_key_last($options);
            }

            $events[] = [
                'studentid'   => (int) $row->userid,
                'optionindex' => $optionindex,
                'timestampms' => (int) $row->timecreatedms,
            ];
        }

        return [
            'students' => array_values($students),
            'options'  => $options,
            'events'   => $events,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'students' => new external_multiple_structure(
                new external_single_structure([
                    'id'   => new external_value(PARAM_INT, 'User id'),
                    'name' => new external_value(PARAM_TEXT, 'Full name'),
                ])
            ),
            'options' => new external_multiple_structure(
                new external_single_structure([
                    'label'    => new external_value(PARAM_TEXT, 'Option text'),
                    'question' => new external_value(PARAM_TEXT, 'Question snippet this option belongs to, empty for Choice activities'),
                ])
            ),
            'events' => new external_multiple_structure(
                new external_single_structure([
                    'studentid'   => new external_value(PARAM_INT, 'User id'),
                    'optionindex' => new external_value(PARAM_INT, 'Index into the options array'),
                    'timestampms' => new external_value(PARAM_INT, 'Client-side Date.now() when the click happened'),
                ])
            ),
        ]);
    }
}
