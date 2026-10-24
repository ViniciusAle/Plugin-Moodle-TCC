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
 * Returns the "writing behaviour in essay questions" data of one quiz, for
 * the indicator on analytics.php.
 *
 * With userid = 0 it returns one summary per student and question. With a
 * userid it additionally returns that student's text-size evolution and
 * individual pastes, so the (larger) timelines are only sent for the one
 * student the teacher actually opens.
 *
 * A student may have several attempts and several page visits per question.
 * Only the LATEST attempt (highest usageid) of each question counts, and
 * within it every page visit is summed - mixing different attempts would
 * blur "how did they write this answer".
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_essay_behavior extends external_api {

    /** @var int Hard cap on rows fetched, to bound worst-case query cost. */
    const MAX_ROWS = 20000;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'cmid'     => new external_value(PARAM_INT, 'Quiz course module id'),
            'userid'   => new external_value(PARAM_INT, 'Student whose evolution to include, 0 for none', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $courseid, int $cmid, int $userid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid'     => $cmid,
            'userid'   => $userid,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/latracker:viewanalytics', $context);

        global $DB;

        $sql = "SELECT e.id, e.userid, e.usageid, e.slot, e.questionnumber, e.questioncontext, e.reportms,
                       e.focusseconds, e.typingseconds, e.keystrokes, e.pausecount, e.pastecount,
                       e.pastedchars, e.finalchars, e.finalwords,
                       " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname
                  FROM {local_latracker_essay} e
                  JOIN {user} u ON u.id = e.userid
                 WHERE e.courseid = :courseid AND e.cmid = :cmid
              ORDER BY e.id ASC";
        $rows = $DB->get_records_sql($sql, ['courseid' => $params['courseid'], 'cmid' => $params['cmid']], 0, self::MAX_ROWS);

        $latestusage = [];
        foreach ($rows as $row) {
            $key = $row->userid . '|' . $row->slot;
            if (!isset($latestusage[$key]) || $row->usageid > $latestusage[$key]) {
                $latestusage[$key] = (int) $row->usageid;
            }
        }

        $students = [];
        $questions = [];
        $summaries = [];
        $adminchecked = [];

        foreach ($rows as $row) {
            $key = $row->userid . '|' . $row->slot;
            if ((int) $row->usageid !== $latestusage[$key]) {
                continue;
            }

            if (!isset($adminchecked[$row->userid])) {
                $adminchecked[$row->userid] = is_siteadmin($row->userid);
            }
            if ($adminchecked[$row->userid]) {
                continue;
            }

            $students[$row->userid] = ['id' => (int) $row->userid, 'name' => $row->fullname];

            if (!isset($questions[$row->slot])) {
                $questions[$row->slot] = ['slot' => (int) $row->slot, 'number' => '', 'context' => ''];
            }
            if ($row->questionnumber !== null && $row->questionnumber !== '') {
                $questions[$row->slot]['number'] = $row->questionnumber;
            }
            if ($row->questioncontext !== null && $row->questioncontext !== '') {
                $questions[$row->slot]['context'] = $row->questioncontext;
            }

            if (!isset($summaries[$key])) {
                $summaries[$key] = [
                    'studentid'     => (int) $row->userid,
                    'slot'          => (int) $row->slot,
                    'sessions'      => 0,
                    'focusseconds'  => 0,
                    'typingseconds' => 0,
                    'keystrokes'    => 0,
                    'pausecount'    => 0,
                    'pastecount'    => 0,
                    'pastedchars'   => 0,
                    'finalchars'    => 0,
                    'finalwords'    => 0,
                    'lastreportms'  => -1,
                ];
            }

            $summary = &$summaries[$key];
            $summary['sessions']++;
            $summary['focusseconds'] += (int) $row->focusseconds;
            $summary['typingseconds'] += (int) $row->typingseconds;
            $summary['keystrokes'] += (int) $row->keystrokes;
            $summary['pausecount'] += (int) $row->pausecount;
            $summary['pastecount'] += (int) $row->pastecount;
            $summary['pastedchars'] += (int) $row->pastedchars;

            // The answer's final size is whatever the most recent report saw.
            if ((int) $row->reportms >= $summary['lastreportms']) {
                $summary['lastreportms'] = (int) $row->reportms;
                $summary['finalchars'] = (int) $row->finalchars;
                $summary['finalwords'] = (int) $row->finalwords;
            }
            unset($summary);
        }

        foreach ($summaries as &$summary) {
            // Capped at 100: pasted text can later be deleted, so pasted
            // characters may exceed the final length.
            $summary['pastedpercent'] = log_trail::pasted_percent($summary['pastedchars'], $summary['finalchars']);
            unset($summary['lastreportms']);
        }
        unset($summary);

        ksort($questions);

        return [
            'questions'  => array_values($questions),
            'students'   => array_values($students),
            'summaries'  => array_values($summaries),
            'evolution'  => $params['userid'] ? self::get_evolution($params['courseid'], $params['cmid'], $params['userid']) : [],
        ];
    }

    /**
     * One student's text-size snapshots and pastes per question (latest
     * attempt only), converted to absolute client timestamps (ms) and
     * merged across every page visit of that attempt.
     *
     * @return array<int, array{slot:int, points:string, pastes:string}>
     */
    private static function get_evolution(int $courseid, int $cmid, int $userid): array {
        global $DB;

        $rows = $DB->get_records_select(
            'local_latracker_essay',
            'courseid = :courseid AND cmid = :cmid AND userid = :userid',
            ['courseid' => $courseid, 'cmid' => $cmid, 'userid' => $userid],
            'startedms ASC',
            'id, usageid, slot, startedms, timeline, pastelog'
        );

        $latestusage = [];
        foreach ($rows as $row) {
            if (!isset($latestusage[$row->slot]) || $row->usageid > $latestusage[$row->slot]) {
                $latestusage[$row->slot] = (int) $row->usageid;
            }
        }

        $points = [];
        $pastes = [];
        foreach ($rows as $row) {
            if ((int) $row->usageid !== $latestusage[$row->slot]) {
                continue;
            }

            foreach (json_decode($row->timeline ?? '[]', true) ?: [] as $point) {
                $points[$row->slot][] = [(int) $row->startedms + ((int) $point[0] * 1000), (int) $point[1], (int) $point[2]];
            }
            foreach (json_decode($row->pastelog ?? '[]', true) ?: [] as $paste) {
                $pastes[$row->slot][] = [(int) $row->startedms + ((int) $paste[0] * 1000), (int) $paste[1]];
            }
        }

        $evolution = [];
        foreach ($latestusage as $slot => $usageid) {
            $slotpoints = $points[$slot] ?? [];
            $slotpastes = $pastes[$slot] ?? [];
            usort($slotpoints, function($a, $b) {
                return $a[0] <=> $b[0];
            });
            usort($slotpastes, function($a, $b) {
                return $a[0] <=> $b[0];
            });
            $evolution[] = [
                'slot'   => (int) $slot,
                'points' => json_encode($slotpoints),
                'pastes' => json_encode($slotpastes),
            ];
        }

        return $evolution;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'slot'    => new external_value(PARAM_INT, 'Question slot'),
                    'number'  => new external_value(PARAM_TEXT, 'Question number as displayed'),
                    'context' => new external_value(PARAM_TEXT, 'Short snippet of the question text'),
                ])
            ),
            'students' => new external_multiple_structure(
                new external_single_structure([
                    'id'   => new external_value(PARAM_INT, 'User id'),
                    'name' => new external_value(PARAM_TEXT, 'Full name'),
                ])
            ),
            'summaries' => new external_multiple_structure(
                new external_single_structure([
                    'studentid'     => new external_value(PARAM_INT, 'User id'),
                    'slot'          => new external_value(PARAM_INT, 'Question slot'),
                    'sessions'      => new external_value(PARAM_INT, 'Page visits summed'),
                    'focusseconds'  => new external_value(PARAM_INT, 'Seconds with the field focused'),
                    'typingseconds' => new external_value(PARAM_INT, 'Seconds of active typing'),
                    'keystrokes'    => new external_value(PARAM_INT, 'Typing/deleting input events'),
                    'pausecount'    => new external_value(PARAM_INT, 'Number of pauses'),
                    'pastecount'    => new external_value(PARAM_INT, 'Number of pastes'),
                    'pastedchars'   => new external_value(PARAM_INT, 'Total characters pasted'),
                    'finalchars'    => new external_value(PARAM_INT, 'Final answer length in characters'),
                    'finalwords'    => new external_value(PARAM_INT, 'Final answer length in words'),
                    'pastedpercent' => new external_value(PARAM_INT, 'Estimated share of the answer that was pasted, 0-100'),
                ])
            ),
            'evolution' => new external_multiple_structure(
                new external_single_structure([
                    'slot'   => new external_value(PARAM_INT, 'Question slot'),
                    'points' => new external_value(PARAM_RAW, 'JSON [[timestampMs, chars, words], ...]'),
                    'pastes' => new external_value(PARAM_RAW, 'JSON [[timestampMs, chars], ...]'),
                ])
            ),
        ]);
    }
}
