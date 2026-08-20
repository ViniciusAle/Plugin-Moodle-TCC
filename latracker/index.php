<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Teacher dashboard: Google Drive OAuth panel, CSV picker and shortcuts.
 *
 * Access: only users holding local/latracker:view in the course context
 * may reach this page - by default that is 'editingteacher' and
 * 'manager', and site administrators always pass capability checks
 * regardless of role assignment.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/latracker/classes/google_drive_client.php');

use local_latracker\google_drive_client;
use local_latracker\output\dashboard_page;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);

require_login($course);
$context = context_course::instance($course->id);
require_capability('local/latracker:view', $context);

$pageurl = new moodle_url('/local/latracker/index.php', ['courseid' => $courseid]);
$analyticsurl = new moodle_url('/local/latracker/analytics.php', ['courseid' => $courseid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('dashboardtitle', 'local_latracker', $course->shortname));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('pluginname', 'local_latracker'), $pageurl);

$connected = false;
$connecturl = $pageurl;
$canmanageintegrations = has_capability('local/latracker:manageintegrations', $context);

if ($canmanageintegrations && google_drive_client::get_configured_issuer()) {
    $drive = new google_drive_client($pageurl);
    $connected = $drive->is_connected();
    $connecturl = $drive->get_login_url();
}

$output = $PAGE->get_renderer('local_latracker');

echo $output->header();

if (!google_drive_client::get_configured_issuer() && $canmanageintegrations) {
    echo $output->notification(get_string('nooauthissuer', 'local_latracker'), \core\output\notification::NOTIFY_WARNING);
}

$page = new dashboard_page($courseid, $connected, $canmanageintegrations, $connecturl, $analyticsurl);
echo $output->render_dashboard_page($page);

echo $output->footer();
