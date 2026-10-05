<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_latracker;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared row-building logic for the "student navigation trail" feature,
 * used by both the single-student and the "all students" external
 * functions (classes/external/get_student_logs.php and
 * get_all_student_logs.php) so the two stay in sync.
 *
 * Each trail combines two sources for one student in one course:
 * - Moodle's own log store (core\log\sql_reader), for every native event; and
 * - this plugin's own local_latracker_click records, turned into a plain
 *   sentence such as "Selected 'D. it works' on question 2", using the
 *   captured option text as-is (rather than a synthesised A/B/C/D letter,
 *   since the configured answer numbering style - none, 123, abc, roman
 *   numerals - can't always be inferred back from stored data).
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_trail {

    /** @var int Hard cap on rows per student, to keep responses and CSVs manageable. */
    const MAX_ROWS_PER_STUDENT = 1000;

    /**
     * Converts native log events into row arrays.
     *
     * @param iterable|\core\event\base[] $events
     * @return array<int, array{time:int, context:string, component:string, eventname:string}>
     */
    public static function build_native_rows(iterable $events): array {
        $rows = [];
        foreach ($events as $event) {
            $rows[] = [
                'time'      => (int) $event->timecreated,
                'context'   => self::describe_context($event),
                'component' => self::describe_component_from_eventname($event->eventname),
                'eventname' => $event->get_name(),
            ];
        }
        return $rows;
    }

    /**
     * Converts this plugin's own option-click records (already filtered
     * to one student, one course, optionlabel not null) into row arrays,
     * numbering the questions in the order this student first encountered
     * them within each activity.
     *
     * @param array $clickrecords stdClass{cmid, optionlabel, questioncontext, timecreatedms}, any order.
     * @param \course_modinfo $modinfo
     * @return array<int, array{time:int, context:string, component:string, eventname:string}>
     */
    public static function build_click_rows(array $clickrecords, \course_modinfo $modinfo): array {
        usort($clickrecords, function($a, $b) {
            return $a->timecreatedms <=> $b->timecreatedms;
        });

        $questionordinals = []; // cmid => [questioncontext => ordinal]
        $rows = [];

        foreach ($clickrecords as $record) {
            $cm = $modinfo->cms[$record->cmid] ?? null;
            $context = '';
            $component = '';
            if ($cm) {
                $context = $cm->get_formatted_name();
                if (get_string_manager()->string_exists('pluginname', 'mod_' . $cm->modname)) {
                    $component = get_string('pluginname', 'mod_' . $cm->modname);
                } else {
                    $component = $cm->modname;
                }
            }

            $question = $record->questioncontext ?? '';
            if ($question !== '') {
                if (!isset($questionordinals[$record->cmid])) {
                    $questionordinals[$record->cmid] = [];
                }
                if (!isset($questionordinals[$record->cmid][$question])) {
                    $questionordinals[$record->cmid][$question] = count($questionordinals[$record->cmid]) + 1;
                }
                $ordinal = $questionordinals[$record->cmid][$question];
                $eventname = get_string('clickeventwithquestion', 'local_latracker', (object) [
                    'option'  => $record->optionlabel,
                    'number'  => $ordinal,
                ]);
            } else {
                $eventname = get_string('clickeventwithoutquestion', 'local_latracker', $record->optionlabel);
            }

            $rows[] = [
                'time'      => (int) round($record->timecreatedms / 1000),
                'context'   => $context,
                'component' => $component,
                'eventname' => $eventname,
            ];
        }

        return $rows;
    }

    /**
     * Merges native and click-derived rows for one student, sorts them
     * chronologically, formats the time column, and applies the row cap.
     *
     * @return array{rows: array, truncated: bool}
     */
    public static function merge_rows(array $nativerows, array $clickrows): array {
        $rows = array_merge($nativerows, $clickrows);
        usort($rows, function($a, $b) {
            return $a['time'] <=> $b['time'];
        });

        $truncated = count($rows) > self::MAX_ROWS_PER_STUDENT;
        $rows = array_slice($rows, 0, self::MAX_ROWS_PER_STUDENT);

        foreach ($rows as &$row) {
            $row['timeformatted'] = userdate($row['time'], get_string('strftimedatetimeshort', 'langconfig'));
        }
        unset($row);

        return ['rows' => $rows, 'truncated' => $truncated];
    }

    /**
     * Human-readable name of the context the event happened in (e.g. an
     * activity or course name). get_context() returns false (not an
     * exception) when the context no longer exists - e.g. a since-deleted
     * activity - which must be checked explicitly.
     */
    public static function describe_context(\core\event\base $event): string {
        $context = $event->get_context();
        if (!$context instanceof \context) {
            return '';
        }
        try {
            return $context->get_context_name();
        } catch (\moodle_exception $e) {
            return '';
        }
    }

    /**
     * Human-readable plugin name the event belongs to (e.g. "Quiz" for
     * mod_quiz), matching the "Component" column of core's own Logs
     * report; core events are labelled with the generic "System" string.
     */
    public static function describe_component_from_eventname(string $eventname): string {
        if (preg_match('/^\\\\([a-z0-9_]+)\\\\event\\\\/', $eventname, $matches)) {
            $component = $matches[1];
        } else {
            $component = 'core';
        }

        if ($component === 'core') {
            return get_string('coresystem');
        }

        if (get_string_manager()->string_exists('pluginname', $component)) {
            return get_string('pluginname', $component);
        }

        return $component;
    }
}
