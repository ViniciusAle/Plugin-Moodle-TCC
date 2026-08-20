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
use local_latracker\google_drive_client;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Lists the CSV files available in the connected teacher's Google Drive.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_drive_files extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/latracker:manageintegrations', $context);

        $returnurl = new moodle_url('/local/latracker/index.php', ['courseid' => $params['courseid']]);
        $drive = new google_drive_client($returnurl);

        if (!$drive->is_connected()) {
            return ['connected' => false, 'files' => []];
        }

        $files = [];
        foreach ($drive->list_csv_files() as $file) {
            $files[] = [
                'id'           => $file->id,
                'name'         => $file->name,
                'modifiedtime' => $file->modifiedTime ?? '',
                'size'         => isset($file->size) ? (int) $file->size : 0,
            ];
        }

        return ['connected' => true, 'files' => $files];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'connected' => new external_value(PARAM_BOOL, 'Whether the teacher has a valid Google session'),
            'files'     => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_RAW, 'Google Drive file id'),
                    'name'         => new external_value(PARAM_TEXT, 'File name'),
                    'modifiedtime' => new external_value(PARAM_RAW, 'Last modified time (ISO 8601)'),
                    'size'         => new external_value(PARAM_INT, 'File size in bytes'),
                ])
            ),
        ]);
    }
}
