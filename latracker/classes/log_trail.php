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
 * Each trail combines three sources for one student in one course:
 * - Moodle's own log store (core\log\sql_reader), for every native event;
 * - this plugin's own local_latracker_essay records (time typing and pastes
 *   in essay questions), turned into one summary row per page visit plus
 *   one row per paste; and
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

    /**
     * Converts native log events into row arrays.
     *
     * Every row carries 'time' (seconds, for display) and 'timems'
     * (milliseconds, only used by merge_rows() to order rows that happen
     * within the same second, e.g. quick successive clicks).
     *
     * @param iterable|\core\event\base[] $events
     * @return array<int, array{time:int, timems:int, context:string, component:string, eventname:string}>
     */
    public static function build_native_rows(iterable $events): array {
        $rows = [];
        foreach ($events as $event) {
            $rows[] = [
                'time'      => (int) $event->timecreated,
                'timems'    => (int) $event->timecreated * 1000,
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
            [$context, $component] = self::describe_activity($modinfo->cms[$record->cmid] ?? null);

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
                'timems'    => (int) $record->timecreatedms,
                'context'   => $context,
                'component' => $component,
                'eventname' => $eventname,
            ];
        }

        return $rows;
    }

    /**
     * Converts the essay writing-behaviour records (local_latracker_essay,
     * already filtered to one student and one course) into row arrays: one
     * summary row per page visit that involved typing, plus one row per
     * individual paste at the moment it happened. Pastes use the question
     * number as displayed to the student.
     *
     * @param array $records stdClass{cmid, slot, questionnumber, startedms, reportms, keystrokes,
     *                       typingseconds, pausecount, pastecount, pastedchars, finalchars,
     *                       finalwords, pastelog}.
     * @param \course_modinfo $modinfo
     * @return array<int, array{time:int, context:string, component:string, eventname:string}>
     */
    public static function build_essay_rows(array $records, \course_modinfo $modinfo): array {
        $rows = [];

        foreach ($records as $record) {
            [$context, $component] = self::describe_activity($modinfo->cms[(int) $record->cmid] ?? null);

            $number = ($record->questionnumber !== null && $record->questionnumber !== '')
                ? $record->questionnumber
                : $record->slot;

            if ((int) $record->keystrokes > 0) {
                $a = (object) [
                    'typing' => format_time(max(1, (int) $record->typingseconds)),
                    'number' => $number,
                    'words'  => (int) $record->finalwords,
                    'pauses' => (int) $record->pausecount,
                    'percent' => self::pasted_percent((int) $record->pastedchars, (int) $record->finalchars),
                ];
                $rows[] = [
                    'time'      => (int) round($record->reportms / 1000),
                    'timems'    => (int) $record->reportms,
                    'context'   => $context,
                    'component' => $component,
                    'eventname' => get_string(
                        (int) $record->pastedchars > 0 ? 'essaytypingeventpasted' : 'essaytypingevent',
                        'local_latracker',
                        $a
                    ),
                ];
            }

            foreach (json_decode($record->pastelog ?? '[]', true) ?: [] as $paste) {
                $rows[] = [
                    'time'      => (int) round($record->startedms / 1000) + (int) $paste[0],
                    'timems'    => (int) $record->startedms + ((int) $paste[0] * 1000),
                    'context'   => $context,
                    'component' => $component,
                    'eventname' => get_string('essaypasteevent', 'local_latracker', (object) [
                        'chars'  => (int) $paste[1],
                        'number' => $number,
                    ]),
                ];
            }
        }

        return $rows;
    }

    /**
     * Share of the final answer that came from pastes, as 0-100 (capped,
     * since pasted text may later be deleted).
     */
    public static function pasted_percent(int $pastedchars, int $finalchars): int {
        if ($pastedchars <= 0) {
            return 0;
        }
        if ($finalchars <= 0) {
            return 100;
        }
        return min(100, (int) round($pastedchars * 100 / $finalchars));
    }

    /**
     * The "Context" (activity name) and "Component" (human-readable module
     * name) columns for a row that belongs to a course module. Both are
     * empty when the module no longer exists.
     *
     * @return array{0:string, 1:string}
     */
    private static function describe_activity(?\cm_info $cm): array {
        if (!$cm) {
            return ['', ''];
        }

        $context = $cm->get_formatted_name();
        if (get_string_manager()->string_exists('pluginname', 'mod_' . $cm->modname)) {
            $component = get_string('pluginname', 'mod_' . $cm->modname);
        } else {
            $component = $cm->modname;
        }

        return [$context, $component];
    }

    /**
     * Merges the native rows with the plugin's own rows (clicks, essay
     * writing) for one student, sorts them NEWEST FIRST and formats the
     * time column. There is deliberately no row cap: the trail and its CSV
     * exports show the student's complete history.
     *
     * Rows are ordered by millisecond time. Rows with the same time (several
     * log events in the same second) keep their original relative order,
     * reversed along with everything else, so the newest still comes first.
     *
     * @return array{rows: array}
     */
    public static function merge_rows(array $nativerows, array $otherrows): array {
        $rows = array_merge($nativerows, $otherrows);

        foreach ($rows as $index => &$row) {
            $row['seq'] = $index;
        }
        unset($row);

        usort($rows, function($a, $b) {
            return [$b['timems'], $b['seq']] <=> [$a['timems'], $a['seq']];
        });

        foreach ($rows as &$row) {
            $row['timeformatted'] = userdate($row['time'], get_string('strftimedatetimeshort', 'langconfig'));
        }
        unset($row);

        return ['rows' => $rows];
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
