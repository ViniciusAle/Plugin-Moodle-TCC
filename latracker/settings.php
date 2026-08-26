<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin settings for local_latracker.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_latracker', get_string('pluginname', 'local_latracker'));
    $ADMIN->add('localplugins', $settings);

    // The Google Drive file picker runs entirely in the browser (Google
    // Identity Services + the Google Picker widget), so the Moodle server
    // never has to call the Google APIs itself - it only receives the
    // already-downloaded CSV content from the browser. This avoids relying
    // on the Moodle server having outbound network access to Google, and
    // avoids the server-side OAuth2 redirect dance entirely.
    $settings->add(new admin_setting_heading(
        'local_latracker/pickerheading',
        get_string('pickerheading', 'local_latracker'),
        get_string('pickerheading_desc', 'local_latracker')
    ));

    $settings->add(new admin_setting_configtext(
        'local_latracker/googleclientid',
        get_string('googleclientid', 'local_latracker'),
        get_string('googleclientid_desc', 'local_latracker'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_latracker/pickerapikey',
        get_string('pickerapikey', 'local_latracker'),
        get_string('pickerapikey_desc', 'local_latracker'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_latracker/trackingenabled',
        get_string('trackingenabled', 'local_latracker'),
        get_string('trackingenabled_desc', 'local_latracker'),
        1
    ));
}
