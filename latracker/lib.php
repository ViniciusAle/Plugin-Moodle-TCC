<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library callbacks for local_latracker.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Injects the student tracking AMD module into every page footer.
 *
 * This is the standard Moodle callback for adding footer JS from a plugin
 * (core calls it from standard_footer_html() for every plugin implementing
 * it), so no direct hook into student pages is required. Tracking is only
 * initialised for logged in, non-guest users, inside a real course, and
 * only when the user holds local/latracker:track in that course context -
 * this keeps the capability check server-authoritative rather than relying
 * on the client to decide whether it should report events.
 *
 * @return void
 */
function local_latracker_before_footer(): void {
    global $PAGE, $USER, $COURSE;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    if (empty($COURSE->id) || $COURSE->id == SITEID) {
        return;
    }

    if (!get_config('local_latracker', 'trackingenabled')) {
        return;
    }

    $context = context_course::instance($COURSE->id, IGNORE_MISSING);
    if (!$context || !has_capability('local/latracker:track', $context)) {
        return;
    }

    $cmid = 0;
    if ($PAGE->cm) {
        $cmid = $PAGE->cm->id;
    }

    $PAGE->requires->js_call_amd('local_latracker/tracker', 'init', [[
        'courseid' => (int) $COURSE->id,
        'cmid'     => (int) $cmid,
        'pageurl'  => $PAGE->url ? $PAGE->url->out(false) : '',
    ]]);
}

/**
 * Adds "LA Dashboard" and "Insights" shortcuts to the course secondary
 * navigation for users who can see the plugin's pages.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 * @return void
 */
function local_latracker_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context): void {
    if (!has_capability('local/latracker:view', $context)) {
        return;
    }

    $dashboardurl = new moodle_url('/local/latracker/index.php', ['courseid' => $course->id]);
    $navigation->add(
        get_string('pluginname', 'local_latracker'),
        $dashboardurl,
        navigation_node::TYPE_SETTING,
        null,
        'latrackerdashboard',
        new pix_icon('i/report', '')
    );

    if (has_capability('local/latracker:viewanalytics', $context)) {
        $analyticsurl = new moodle_url('/local/latracker/analytics.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('insights', 'local_latracker'),
            $analyticsurl,
            navigation_node::TYPE_SETTING,
            null,
            'latrackerinsights',
            new pix_icon('i/stats', '')
        );
    }
}
