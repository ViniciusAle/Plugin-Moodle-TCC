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
use local_latracker\log_trail;

defined('MOODLE_INTERNAL') || die();

/**
 * Returns one student's navigation trail in a course, for the "Student
 * navigation trail" insight: Moodle's own log store (the same data
 * behind Site administration > Reports > Logs), merged chronologically
 * with this plugin's own recorded quiz/choice option clicks.
 *
 * This goes through the core log reader API (\core\log\sql_reader), not
 * a raw query against logstore_standard_log, so it keeps working on a
 * site configured with a different log store plugin.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_student_logs extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'userid'   => new external_value(PARAM_INT, 'The student whose trail to fetch'),
        ]);
    }

    public static function execute(int $courseid, int $userid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userid'   => $userid,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/latracker:viewanalytics', $context);

        global $DB;

        $nativerows = self::fetch_native_rows($params['courseid'], $params['userid']);

        // 'id' must be included (and first) in the field list: get_records_select()
        // uses the first requested field as the array key, and every click on
        // the same activity shares the same cmid - without 'id', rows would
        // silently collapse into one per cmid, hiding all but the last click.
        $clickrecords = array_values($DB->get_records_select(
            'local_latracker_click',
            'courseid = :courseid AND userid = :userid AND optionlabel IS NOT NULL',
            ['courseid' => $params['courseid'], 'userid' => $params['userid']],
            '',
            'id, cmid, optionlabel, questioncontext, timecreatedms'
        ));
        $modinfo = get_fast_modinfo($params['courseid']);
        $clickrows = log_trail::build_click_rows($clickrecords, $modinfo);

        // Same rule as above: 'id' first, or rows sharing a cmid would collapse.
        $essayrecords = array_values($DB->get_records_select(
            'local_latracker_essay',
            'courseid = :courseid AND userid = :userid',
            ['courseid' => $params['courseid'], 'userid' => $params['userid']],
            '',
            'id, cmid, slot, questionnumber, startedms, reportms, keystrokes, typingseconds, pausecount, '
                . 'pastecount, pastedchars, finalchars, finalwords, pastelog'
        ));
        $essayrows = log_trail::build_essay_rows($essayrecords, $modinfo);

        $merged = log_trail::merge_rows($nativerows, array_merge($clickrows, $essayrows));

        return [
            'rows' => array_map(function($row) {
                return [
                    'time'      => $row['timeformatted'],
                    'context'   => $row['context'],
                    'component' => $row['component'],
                    'eventname' => $row['eventname'],
                ];
            }, $merged['rows']),
        ];
    }

    /**
     * @return array<int, array{time:int, context:string, component:string, eventname:string}>
     */
    private static function fetch_native_rows(int $courseid, int $userid): array {
        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers('core\log\sql_reader');
        $reader = reset($readers);
        if (!$reader) {
            return [];
        }

        // limitnum 0 = no limit: the trail shows the student's whole history.
        $events = $reader->get_events_select(
            'courseid = :courseid AND userid = :userid',
            ['courseid' => $courseid, 'userid' => $userid],
            'timecreated ASC',
            0,
            0
        );

        return log_trail::build_native_rows($events);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'time'      => new external_value(PARAM_TEXT, 'Formatted date/time'),
                    'context'   => new external_value(PARAM_TEXT, 'Context the event happened in'),
                    'component' => new external_value(PARAM_TEXT, 'Human-readable plugin name'),
                    'eventname' => new external_value(PARAM_TEXT, 'Event name/description'),
                ])
            ),
        ]);
    }
}
