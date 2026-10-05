<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Event observers for local_latracker.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        // Fires for the "Mark as complete" checkbox (manual or automatic)
        // on any activity type - see classes/observer.php for why this
        // replaced the earlier mod_assign/mod_quiz-only submission events.
        'eventname' => '\core\event\course_module_completion_updated',
        'callback'  => '\local_latracker\observer::completion_updated',
    ],
];
