<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Capability definitions for local_latracker.
 *
 * Access model:
 * - Only 'editingteacher' and 'manager' (which covers site administrators,
 *   since admins bypass all capability checks) can reach the dashboard and
 *   the analytics pages.
 * - 'track' is the capability checked by the AJAX endpoint that receives
 *   events from the student-facing AMD module. It is granted to students
 *   and teachers so their navigation can be recorded, but NOT to guests.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // View the teacher dashboard (Google Drive panel + shortcuts).
    'local/latracker:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // View the Learning Analytics / Insights page.
    'local/latracker:viewanalytics' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Manage the Google Drive integration and trigger CSV imports.
    'local/latracker:manageintegrations' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'riskbitmask'  => RISK_PERSONAL,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Allow an event (page time, activity time, copy/paste) to be tracked
    // for the current user. Checked from the AJAX/external function.
    'local/latracker:track' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'student'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
];
