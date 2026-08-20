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
use local_latracker\csv_importer;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Imports the CSV files a teacher selected in the dashboard checkbox list.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_drive_files extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'fileids'  => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Google Drive file id'),
                'Files selected for import'
            ),
        ]);
    }

    public static function execute(int $courseid, array $fileids): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'fileids'  => $fileids,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/latracker:manageintegrations', $context);

        global $USER;
        $returnurl = new moodle_url('/local/latracker/index.php', ['courseid' => $params['courseid']]);
        $drive = new google_drive_client($returnurl);

        if (!$drive->is_connected()) {
            throw new \moodle_exception('notconnected', 'local_latracker');
        }

        // Only import files the account can currently see, to avoid a
        // tampered fileid list being used to fetch arbitrary Drive ids.
        $available = [];
        foreach ($drive->list_csv_files() as $file) {
            $available[$file->id] = $file->name;
        }

        $imported = [];
        foreach ($params['fileids'] as $fileid) {
            if (!isset($available[$fileid])) {
                continue;
            }
            $content = $drive->download_file($fileid);
            $recordid = csv_importer::import($params['courseid'], $USER->id, $fileid, $available[$fileid], $content);
            $imported[] = ['id' => $recordid, 'filename' => $available[$fileid]];
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
