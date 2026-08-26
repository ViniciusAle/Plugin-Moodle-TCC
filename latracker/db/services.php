<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Web service / AJAX external function definitions for local_latracker.
 *
 * All calls made through core's AJAX dispatcher (lib/ajax/service.php) are
 * automatically required to carry a valid sesskey, which is the mechanism
 * Moodle uses to prevent CSRF on AJAX endpoints - this is why the tracker
 * AMD module does not need to (and must not) send sesskey manually, the
 * core/ajax JS module attaches it for us.
 *
 * There is no "list Google Drive files" function here on purpose: file
 * browsing is done entirely in the browser via the Google Picker widget
 * (amd/src/googlepicker.js), so the Moodle server never has to reach the
 * Google APIs itself - only the already-downloaded CSV content is posted
 * to local_latracker_import_drive_files below.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_latracker_track_event' => [
        'classname'   => 'local_latracker\external\track_event',
        'methodname'  => 'execute',
        'description' => 'Records a single tracking event (page view, activity timer, copy/paste) for the current user.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_latracker_import_drive_files' => [
        'classname'   => 'local_latracker\external\import_drive_files',
        'methodname'  => 'execute',
        'description' => 'Parses and stores CSV file content selected by the teacher via the Google Picker.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
