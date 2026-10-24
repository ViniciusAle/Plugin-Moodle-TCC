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
 * Receives the writing-behaviour metrics of essay questions from the
 * student-facing AMD module (amd/src/tracker.js).
 *
 * Each report is CUMULATIVE for one student, quiz attempt (usageid),
 * question (slot) and page visit (sessionid), so it is stored as an upsert
 * rather than an append. Reports can arrive out of order (a heartbeat and a
 * page-hide beacon in flight together), so an older report never overwrites
 * a newer one (see reportms).
 *
 * Only counts, durations and text SIZES are stored - never the text that
 * was typed or pasted. The text itself already lives in Moodle's own
 * question attempt tables, and copying it here would add nothing to the
 * indicator while widening the privacy footprint.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class track_essay extends external_api {

    /** @var int Hard cap on essay fields per request. */
    const MAX_FIELDS = 20;

    /** @var int Hard cap on stored text-size snapshots per row. */
    const MAX_TIMELINE_POINTS = 200;

    /** @var int Hard cap on stored individual paste events per row. */
    const MAX_PASTES = 100;

    /** @var int Upper bound (seconds) accepted for any duration, as a sanity limit. */
    const MAX_SECONDS = 604800;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'fields'   => new external_multiple_structure(
                new external_single_structure([
                    'sessionid'       => new external_value(PARAM_ALPHANUMEXT, 'Random id of the page visit'),
                    'usageid'         => new external_value(PARAM_INT, 'Question usage id of the quiz attempt', VALUE_DEFAULT, 0),
                    'slot'            => new external_value(PARAM_INT, 'Question slot in the quiz', VALUE_DEFAULT, 0),
                    'cmid'            => new external_value(PARAM_INT, 'Quiz course module id', VALUE_DEFAULT, 0),
                    'questionnumber'  => new external_value(PARAM_TEXT, 'Question number as displayed', VALUE_DEFAULT, ''),
                    'questioncontext' => new external_value(PARAM_RAW, 'Short snippet of the question text', VALUE_DEFAULT, ''),
                    'startedms'       => new external_value(PARAM_INT, 'Client Date.now() at page visit start'),
                    'reportms'        => new external_value(PARAM_INT, 'Client Date.now() of this report'),
                    'focusseconds'    => new external_value(PARAM_INT, 'Seconds with the field focused', VALUE_DEFAULT, 0),
                    'typingseconds'   => new external_value(PARAM_INT, 'Seconds of active typing', VALUE_DEFAULT, 0),
                    'keystrokes'      => new external_value(PARAM_INT, 'Typing/deleting input events', VALUE_DEFAULT, 0),
                    'pausecount'      => new external_value(PARAM_INT, 'Number of pauses', VALUE_DEFAULT, 0),
                    'pastecount'      => new external_value(PARAM_INT, 'Number of pastes', VALUE_DEFAULT, 0),
                    'pastedchars'     => new external_value(PARAM_INT, 'Total characters pasted', VALUE_DEFAULT, 0),
                    'finalchars'      => new external_value(PARAM_INT, 'Answer length in characters', VALUE_DEFAULT, 0),
                    'finalwords'      => new external_value(PARAM_INT, 'Answer length in words', VALUE_DEFAULT, 0),
                    'timeline'        => new external_value(PARAM_RAW, 'JSON [[secs, chars, words], ...]', VALUE_DEFAULT, '[]'),
                    'pastelog'        => new external_value(PARAM_RAW, 'JSON [[secs, chars], ...]', VALUE_DEFAULT, '[]'),
                ]),
                'Cumulative metrics per essay question'
            ),
        ]);
    }

    public static function execute(int $courseid, array $fields): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'fields'   => $fields,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/latracker:track', $context);

        foreach (array_slice($params['fields'], 0, self::MAX_FIELDS) as $field) {
            self::store_field($params['courseid'], $field);
        }

        return ['success' => true];
    }

    /**
     * Inserts or updates the cumulative row for one essay question.
     */
    private static function store_field(int $courseid, array $field): void {
        global $USER, $DB;

        $values = [
            'cmid'            => $field['cmid'] ?: null,
            'questionnumber'  => $field['questionnumber'] !== '' ? \core_text::substr($field['questionnumber'], 0, 20) : null,
            'questioncontext' => $field['questioncontext'] !== '' ? \core_text::substr($field['questioncontext'], 0, 150) : null,
            'reportms'        => max(0, $field['reportms']),
            'focusseconds'    => self::clamp_seconds($field['focusseconds']),
            'typingseconds'   => self::clamp_seconds($field['typingseconds']),
            'keystrokes'      => max(0, $field['keystrokes']),
            'pausecount'      => max(0, $field['pausecount']),
            'pastecount'      => max(0, $field['pastecount']),
            'pastedchars'     => max(0, $field['pastedchars']),
            'finalchars'      => max(0, $field['finalchars']),
            'finalwords'      => max(0, $field['finalwords']),
            'timeline'        => self::sanitize_points($field['timeline'], 3, self::MAX_TIMELINE_POINTS),
            'pastelog'        => self::sanitize_points($field['pastelog'], 2, self::MAX_PASTES),
            'timemodified'    => time(),
        ];

        $key = [
            'userid'    => $USER->id,
            'sessionid' => $field['sessionid'],
            'usageid'   => $field['usageid'],
            'slot'      => $field['slot'],
        ];

        $existing = $DB->get_record('local_latracker_essay', $key);

        if (!$existing) {
            $record = (object) ($key + $values + [
                'courseid'  => $courseid,
                'startedms' => max(0, $field['startedms']),
            ]);
            try {
                $DB->insert_record('local_latracker_essay', $record);
                return;
            } catch (\dml_write_exception $e) {
                // A concurrent report for the same row won the insert (the
                // unique index rejected this one): fall through and update.
                $existing = $DB->get_record('local_latracker_essay', $key);
                if (!$existing) {
                    throw $e;
                }
            }
        }

        // Reports are cumulative: ignore one that is older than what is stored.
        if ($values['reportms'] <= (int) $existing->reportms) {
            return;
        }

        $DB->update_record('local_latracker_essay', (object) (['id' => $existing->id] + $values));
    }

    private static function clamp_seconds(int $seconds): int {
        return max(0, min($seconds, self::MAX_SECONDS));
    }

    /**
     * Validates a client-supplied JSON list of fixed-width integer tuples,
     * and re-encodes it so only well-formed numbers are ever stored.
     *
     * @param string $json
     * @param int $width Expected number of integers per tuple.
     * @param int $max Maximum number of tuples kept.
     * @return string|null JSON, or null when empty/invalid.
     */
    private static function sanitize_points(string $json, int $width, int $max): ?string {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $clean = [];
        foreach ($decoded as $tuple) {
            if (!is_array($tuple) || count($tuple) < $width) {
                continue;
            }
            $row = [];
            for ($i = 0; $i < $width; $i++) {
                $row[] = max(0, (int) $tuple[$i]);
            }
            $clean[] = $row;
            if (count($clean) >= $max) {
                break;
            }
        }

        return $clean ? json_encode($clean) : null;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the reports were stored'),
        ]);
    }
}
