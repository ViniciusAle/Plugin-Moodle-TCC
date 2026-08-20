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
    'local_latracker_get_drive_files' => [
        'classname'   => 'local_latracker\external\get_drive_files',
        'methodname'  => 'execute',
        'description' => 'Lists the CSV files available in the current teacher Google Drive account.',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_latracker_import_drive_files' => [
        'classname'   => 'local_latracker\external\import_drive_files',
        'methodname'  => 'execute',
        'description' => 'Imports and parses the selected CSV files from Google Drive into the plugin tables.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
