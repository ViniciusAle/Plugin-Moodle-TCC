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
 * Receives a batch of mouse click samples from the student-facing AMD
 * module (amd/src/tracker.js). Clicks are buffered client-side and sent
 * in batches (on a heartbeat, and on page hide) rather than one request
 * per click, to keep the volume of AJAX traffic reasonable.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class track_clicks extends external_api {

    /** @var int Hard cap per request, in case a batch grows unexpectedly large. */
    const MAX_BATCH = 200;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'clicks'   => new external_multiple_structure(
                new external_single_structure([
                    'cmid'        => new external_value(PARAM_INT, 'Course module id, 0 if not applicable', VALUE_DEFAULT, 0),
                    'pageurl'     => new external_value(PARAM_URL, 'URL of the page where the click happened', VALUE_DEFAULT, ''),
                    'targettag'   => new external_value(PARAM_RAW, 'Tag name of the clicked element', VALUE_DEFAULT, ''),
                    'x'           => new external_value(PARAM_INT, 'Click X position (viewport-relative)', VALUE_DEFAULT, 0),
                    'y'           => new external_value(PARAM_INT, 'Click Y position (viewport-relative)', VALUE_DEFAULT, 0),
                    'optionlabel' => new external_value(PARAM_RAW, 'Text of the answer/choice option selected, if any', VALUE_DEFAULT, ''),
                    'questioncontext' => new external_value(PARAM_RAW, 'Short snippet identifying the question, if any', VALUE_DEFAULT, ''),
                    'timestampms' => new external_value(PARAM_INT, 'Client-side Date.now() at click time', VALUE_DEFAULT, 0),
                ]),
                'Batched click samples'
            ),
        ]);
    }

    public static function execute(int $courseid, array $clicks): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'clicks'   => $clicks,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/latracker:track', $context);

        global $USER, $DB;
        $now = time();
        $records = [];

        foreach (array_slice($params['clicks'], 0, self::MAX_BATCH) as $click) {
            $records[] = (object) [
                'userid'        => $USER->id,
                'courseid'      => $params['courseid'],
                'cmid'          => $click['cmid'] ?: null,
                'pageurl'       => \core_text::substr($click['pageurl'], 0, 255),
                'targettag'     => \core_text::substr($click['targettag'], 0, 40),
                'clientx'       => $click['x'],
                'clienty'       => $click['y'],
                'optionlabel'   => $click['optionlabel'] !== '' ? \core_text::substr($click['optionlabel'], 0, 150) : null,
                'questioncontext' => $click['questioncontext'] !== '' ? \core_text::substr($click['questioncontext'], 0, 150) : null,
                'timecreatedms' => $click['timestampms'],
                'timecreated'   => $now,
            ];
        }

        if (!empty($records)) {
            $DB->insert_records('local_latracker_click', $records);
        }

        return ['success' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the batch was stored'),
        ]);
    }
}
