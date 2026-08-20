<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Learning Analytics / Insights page.
 *
 * Renders one chart per registered insight in analytics_engine, built
 * from the tracker tables (page time, activity resolution time,
 * copy/paste events) and any CSV data imported from Google Drive.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_latracker\output\analytics_page;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);

require_login($course);
$context = context_course::instance($course->id);
require_capability('local/latracker:viewanalytics', $context);

$pageurl = new moodle_url('/local/latracker/analytics.php', ['courseid' => $courseid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('analyticstitle', 'local_latracker', $course->shortname));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('insights', 'local_latracker'), $pageurl);

$output = $PAGE->get_renderer('local_latracker');

echo $output->header();
echo $output->heading(get_string('insights', 'local_latracker'));

$page = new analytics_page($courseid);
echo $output->render_analytics_page($page);

echo $output->footer();
