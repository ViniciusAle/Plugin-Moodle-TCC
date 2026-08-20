<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English language strings for local_latracker.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Learning Analytics Tracker';
$string['insights'] = 'Learning Analytics Insights';
$string['dashboardtitle'] = 'LA Tracker dashboard: {$a}';
$string['analyticstitle'] = 'Learning Analytics: {$a}';

// Capabilities.
$string['latracker:view'] = 'View the LA Tracker dashboard';
$string['latracker:viewanalytics'] = 'View the Learning Analytics insights page';
$string['latracker:manageintegrations'] = 'Manage the Google Drive integration and import CSV files';
$string['latracker:track'] = 'Have navigation and activity events tracked';

// Dashboard.
$string['googledrivepanel'] = 'Google Drive integration';
$string['connectgoogleexplain'] = 'Connect your Google account to browse and import CSV files from your Google Drive.';
$string['connectgoogledrive'] = 'Connect Google Drive';
$string['connected'] = 'Connected to Google Drive';
$string['refresh'] = 'Refresh';
$string['selectcsvexplain'] = 'Select the CSV files you want to import and process for this course.';
$string['loadingfiles'] = 'Loading files...';
$string['nocsvfiles'] = 'No CSV files were found in this Google Drive account.';
$string['importselected'] = 'Import selected files';
$string['importing'] = 'Importing selected files...';
$string['importsuccess'] = '{$a} file(s) imported successfully.';
$string['shortcuts'] = 'Shortcuts';
$string['gotoinsights'] = 'Go to Learning Analytics Insights';
$string['dashboardhelp'] = 'Once files are imported, open the Insights page to see engagement charts and cross analysis.';
$string['nooauthissuer'] = 'No Google OAuth 2 service is configured yet. Ask a site administrator to set it up under Site administration > Plugins > Local plugins > Learning Analytics Tracker.';
$string['notconnected'] = 'Your Google Drive account is not connected.';

// Analytics.
$string['noinsights'] = 'No insights are registered.';
$string['noinsightdata'] = 'Not enough data has been collected yet to build this chart.';
$string['insight_engagement_title'] = 'Temporal engagement';
$string['insight_engagement_desc'] = 'Total time spent on the platform per student.';
$string['insight_behavior_title'] = 'Task behaviour';
$string['insight_behavior_desc'] = 'Average time to submit versus copy/paste incidence, per activity.';
$string['insight_csvcross_title'] = 'Imported CSV cross analysis';
$string['insight_csvcross_desc'] = 'Rows imported and average of the first numeric column, per imported CSV file.';
$string['minutesonplatform'] = 'Minutes on platform';
$string['avgminutestosubmit'] = 'Avg. minutes to submit';
$string['copypasteevents'] = 'Copy/paste events';
$string['importedrows'] = 'Imported rows';
$string['avgnumericvalue'] = 'Avg. numeric value';

// Settings.
$string['oauthheading'] = 'Google Drive OAuth 2';
$string['oauthheading_desc'] = 'Register a "Google" service under <a href="{$a}">Site administration > Server > OAuth 2 services</a> before choosing it below. See README.md for step-by-step instructions.';
$string['oauthissuer'] = 'Google OAuth 2 issuer';
$string['oauthissuer_desc'] = 'The OAuth 2 service used to authenticate teachers with Google Drive.';
$string['trackingenabled'] = 'Enable student tracking';
$string['trackingenabled_desc'] = 'When disabled, the tracker AMD module is not injected into any page and no events are recorded.';

// Privacy.
$string['privacy:metadata:local_latracker_pageview'] = 'Time spent by a user on a page.';
$string['privacy:metadata:local_latracker_pageview:userid'] = 'The id of the user.';
$string['privacy:metadata:local_latracker_pageview:courseid'] = 'The course where the page view happened.';
$string['privacy:metadata:local_latracker_pageview:pageurl'] = 'The URL of the visited page.';
$string['privacy:metadata:local_latracker_pageview:duration'] = 'How many seconds the user spent on the page.';
$string['privacy:metadata:local_latracker_pageview:timecreated'] = 'When the page view was recorded.';
$string['privacy:metadata:local_latracker_sessiontime'] = 'Accumulated platform usage time of a user in a course.';
$string['privacy:metadata:local_latracker_sessiontime:userid'] = 'The id of the user.';
$string['privacy:metadata:local_latracker_sessiontime:courseid'] = 'The course.';
$string['privacy:metadata:local_latracker_sessiontime:totaltime'] = 'Total accumulated seconds.';
$string['privacy:metadata:local_latracker_activitytime'] = 'Time a user took to resolve an activity, from opening it to submitting it.';
$string['privacy:metadata:local_latracker_activitytime:userid'] = 'The id of the user.';
$string['privacy:metadata:local_latracker_activitytime:cmid'] = 'The activity (course module) id.';
$string['privacy:metadata:local_latracker_activitytime:timestarted'] = 'When the activity was opened.';
$string['privacy:metadata:local_latracker_activitytime:timesubmitted'] = 'When the activity was submitted.';
$string['privacy:metadata:local_latracker_event'] = 'Copy/paste keyboard shortcut usage recorded while navigating or resolving an activity.';
$string['privacy:metadata:local_latracker_event:userid'] = 'The id of the user.';
$string['privacy:metadata:local_latracker_event:eventtype'] = 'Whether the event was a copy or a paste.';
$string['privacy:metadata:local_latracker_event:pageurl'] = 'The URL of the page where the event happened.';
