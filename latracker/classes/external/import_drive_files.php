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
use local_latracker\csv_importer;

defined('MOODLE_INTERNAL') || die();

/**
 * Stores the CSV files a teacher selected and downloaded through the
 * client-side Google Picker (amd/src/googlepicker.js).
 *
 * The browser, not the Moodle server, talks to the Google Drive API: it
 * obtains its own OAuth access token via Google Identity Services, lets
 * the teacher pick files with the Picker widget, downloads their content
 * directly from googleapis.com, and only then posts the raw text here.
 * This keeps the plugin working even when the Moodle server itself has no
 * outbound access to Google (a common cause of the import silently
 * hanging when it was implemented as a server-to-server call).
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_drive_files extends external_api {

    /** @var int Hard cap per file, to keep the JSON POST body reasonable. */
    const MAX_CONTENT_BYTES = 5 * 1024 * 1024;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'files'    => new external_multiple_structure(
                new external_single_structure([
                    'driveid'  => new external_value(PARAM_RAW, 'Google Drive file id'),
                    'filename' => new external_value(PARAM_TEXT, 'File name'),
                    'content'  => new external_value(PARAM_RAW, 'Raw CSV text content'),
                ]),
                'Files selected and downloaded by the browser'
            ),
        ]);
    }

    public static function execute(int $courseid, array $files): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'files'    => $files,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/latracker:manageintegrations', $context);

        global $USER;
        $imported = [];

        foreach ($params['files'] as $file) {
            if (\core_text::strlen($file['content']) > self::MAX_CONTENT_BYTES) {
                continue;
            }
            $recordid = csv_importer::import(
                $params['courseid'],
                $USER->id,
                $file['driveid'],
                $file['filename'],
                $file['content']
            );
            $imported[] = ['id' => $recordid, 'filename' => $file['filename']];
        }

        return ['imported' => $imported];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'imported' => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_INT, 'local_latracker_drivefile id'),
                    'filename' => new external_value(PARAM_TEXT, 'File name'),
                ])
            ),
        ]);
    }
}
