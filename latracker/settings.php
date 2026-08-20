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

    $settings->add(new admin_setting_heading(
        'local_latracker/oauthheading',
        get_string('oauthheading', 'local_latracker'),
        get_string('oauthheading_desc', 'local_latracker', (new moodle_url('/admin/tool/oauth2/issuers.php'))->out(false))
    ));

    // Build the list of configured OAuth2 issuers so the admin can pick
    // the one registered for Google (created under Site administration >
    // Server > OAuth 2 services, see README.md for the exact steps).
    $issueroptions = [0 => get_string('choose')];
    if (class_exists('\core\oauth2\api')) {
        foreach (\core\oauth2\api::get_all_issuers() as $issuer) {
            $issueroptions[$issuer->get('id')] = $issuer->get('name') . ' (' . $issuer->get('baseurl') . ')';
        }
    }

    $settings->add(new admin_setting_configselect(
        'local_latracker/oauthissuerid',
        get_string('oauthissuer', 'local_latracker'),
        get_string('oauthissuer_desc', 'local_latracker'),
        0,
        $issueroptions
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_latracker/trackingenabled',
        get_string('trackingenabled', 'local_latracker'),
        get_string('trackingenabled_desc', 'local_latracker'),
        1
    ));
}
