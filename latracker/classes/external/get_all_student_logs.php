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
 * Returns every enrolled student's navigation trail in a course, in one
 * response, for the "download all students" CSV button - the same rows
 * as local_latracker_get_student_logs, one student after another, each
 * row carrying the student's name so they can be told apart in a single
 * combined file.
 *
 * Site administrators are always excluded (see local_latracker_before_footer()
 * in lib.php for why: their own testing/administration clicks are not
 * meant to be treated as student data), one query fetches every
 * relevant native log event and one fetches every relevant click record,
 * rather than looping a query per student.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_all_student_logs extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/latracker:viewanalytics', $context);

        global $DB;

        $students = [];
        foreach (get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname', 'u.lastname, u.firstname') as $user) {
            if (is_siteadmin($user->id)) {
                continue;
            }
            $students[$user->id] = fullname($user);
        }

        if (empty($students)) {
            return ['rows' => []];
        }

        $userids = array_keys($students);
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $nativebyuser = self::fetch_native_rows_by_user($params['courseid'], $userids, $insql, $inparams);

        // 'id' must be included (and first) in the field list: get_records_select()
        // uses the first requested field as the array key, and many rows here
        // share the same userid - without 'id', rows would silently collapse
        // down to one per student, hiding all but their last click.
        $clickrecords = $DB->get_records_select(
            'local_latracker_click',
            "courseid = :courseid AND optionlabel IS NOT NULL AND userid $insql",
            array_merge(['courseid' => $params['courseid']], $inparams),
            '',
            'id, userid, cmid, optionlabel, questioncontext, timecreatedms'
        );
        $clicksbyuser = [];
        foreach ($clickrecords as $record) {
            $clicksbyuser[$record->userid][] = $record;
        }

        // Same rule as above: 'id' first, or rows sharing a userid would collapse.
        $essayrecords = $DB->get_records_select(
            'local_latracker_essay',
            "courseid = :courseid AND userid $insql",
            array_merge(['courseid' => $params['courseid']], $inparams),
            '',
            'id, userid, cmid, slot, questionnumber, startedms, reportms, keystrokes, typingseconds, pausecount, '
                . 'pastecount, pastedchars, finalchars, finalwords, pastelog'
        );
        $essaybyuser = [];
        foreach ($essayrecords as $record) {
            $essaybyuser[$record->userid][] = $record;
        }

        $modinfo = get_fast_modinfo($params['courseid']);
        $rows = [];

        foreach ($students as $userid => $studentname) {
            $nativerows = $nativebyuser[$userid] ?? [];
            $clickrows = log_trail::build_click_rows($clicksbyuser[$userid] ?? [], $modinfo);
            $essayrows = log_trail::build_essay_rows($essaybyuser[$userid] ?? [], $modinfo);
            $merged = log_trail::merge_rows($nativerows, array_merge($clickrows, $essayrows));

            foreach ($merged['rows'] as $row) {
                $rows[] = [
                    'student'   => $studentname,
                    'time'      => $row['timeformatted'],
                    'context'   => $row['context'],
                    'component' => $row['component'],
                    'eventname' => $row['eventname'],
                ];
            }
        }

        return ['rows' => $rows];
    }

    /**
     * Fetches every relevant native log event in one query and groups the
     * resulting rows by userid.
     *
     * @return array<int, array>
     */
    private static function fetch_native_rows_by_user(int $courseid, array $userids, string $insql, array $inparams): array {
        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers('core\log\sql_reader');
        $reader = reset($readers);
        if (!$reader) {
            return [];
        }

        // limitnum 0 = no limit: the export contains every student's whole history.
        $events = $reader->get_events_select(
            "courseid = :courseid AND userid $insql",
            array_merge(['courseid' => $courseid], $inparams),
            'userid ASC, timecreated ASC',
            0,
            0
        );

        $byuser = [];
        foreach ($events as $event) {
            $byuser[$event->userid][] = $event;
        }

        foreach ($byuser as $userid => $userevents) {
            $byuser[$userid] = log_trail::build_native_rows($userevents);
        }

        return $byuser;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'student'   => new external_value(PARAM_TEXT, 'Student full name'),
                    'time'      => new external_value(PARAM_TEXT, 'Formatted date/time'),
                    'context'   => new external_value(PARAM_TEXT, 'Context the event happened in'),
                    'component' => new external_value(PARAM_TEXT, 'Human-readable plugin name'),
                    'eventname' => new external_value(PARAM_TEXT, 'Event name/description'),
                ])
            ),
        ]);
    }
}
