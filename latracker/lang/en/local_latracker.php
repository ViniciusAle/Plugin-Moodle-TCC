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
$string['selectcsvexplain'] = 'Select the CSV files you want to import and process for this course. A Google window will open for you to sign in and pick the files.';
$string['selectfromdrive'] = 'Select CSV files from Google Drive';
$string['opening'] = 'Opening Google Drive...';
$string['nofileselected'] = 'No file was selected.';
$string['importing'] = 'Downloading and importing the selected files...';
$string['importsuccess'] = '{$a} file(s) imported successfully.';
$string['shortcuts'] = 'Shortcuts';
$string['gotoinsights'] = 'Go to Learning Analytics Insights';
$string['dashboardhelp'] = 'Once files are imported, open the Insights page to see the learning analytics dashboard.';
$string['nogoogleconfig'] = 'The Google Drive picker is not configured yet. Ask a site administrator to set the Client ID and Picker API key under Site administration > Plugins > Local plugins > Learning Analytics Tracker.';

// Analytics.
$string['noinsights'] = 'No insights are registered.';
$string['noinsightdata'] = 'Not enough data has been collected yet to build this chart.';
$string['kpi_students'] = 'Students tracked';
$string['kpi_avgminutes'] = 'Avg. minutes per student';
$string['kpi_activities'] = 'Activities with completion data';
$string['kpi_late'] = 'Late completions';
$string['section_engagement'] = 'Engagement over time';
$string['section_completion'] = 'Completion & deadlines';
$string['section_questions'] = 'Answer interactions';
$string['section_trail'] = 'Student navigation trail';
$string['section_behaviour'] = 'Behaviour indicators';
$string['aboutindicator'] = 'About this indicator';
$string['insight_engagementtimeline_title'] = 'Engagement over time';
$string['insight_engagementtimeline_desc'] = 'Cumulative minutes on the platform per student, day by day. Use the filter to choose which students are shown.';
$string['selectstudents'] = 'Filter students';
$string['selectall'] = 'Select all';
$string['clearselection'] = 'Clear selection';
$string['insight_behavior_title'] = 'Task behaviour';
$string['insight_behavior_desc'] = 'Average time to submit versus copy/paste incidence, per activity.';
$string['insight_csvcross_title'] = 'Imported CSV cross analysis';
$string['insight_csvcross_desc'] = 'Rows imported and average of the first numeric column, per imported CSV file.';
$string['insight_clickengagement_title'] = 'Click engagement';
$string['insight_clickengagement_desc'] = 'Total mouse clicks per student - a proxy for active interaction, as opposed to just having the tab open.';
$string['insight_rageclick_title'] = 'Rage clicks (difficulty signal)';
$string['insight_rageclick_desc'] = 'Bursts of several clicks in quick succession and close together on screen, per activity - a common sign of confusion or something not responding.';
$string['insight_submissiontiming_title'] = 'Submission timing';
$string['insight_submissiontiming_desc'] = 'How long before the deadline students actually finished the activity, using Moodle\'s own "Mark as complete" tracking compared to each activity\'s "Expect completed on" date. Works for any activity type with completion tracking enabled, including external tools (LTI).';
$string['insight_optionclicks_title'] = 'Answer selection patterns';
$string['insight_optionclicks_desc'] = 'Which answer/choice option students clicked on a quiz or Choice activity. Only multiple-choice/true-false questions and Choice activities produce data here.';
$string['nooptionclickactivities'] = 'This course has no Quiz or Choice activities yet.';
$string['nooptionclickdata'] = 'No option clicks have been recorded for this activity yet.';
$string['selectactivity'] = 'Activity';
$string['selectactivities'] = 'Filter activities';
$string['loadingoptions'] = 'Loading...';
$string['viewsequence'] = 'View one student\'s click sequence';
$string['finalclick'] = 'final answer';
$string['nosequencedata'] = 'This student has no recorded clicks on this activity.';
$string['insight_studentlogs_title'] = 'Student navigation trail';
$string['insight_studentlogs_desc'] = 'One student\'s own activity, read from Moodle\'s native logs, organised in a table - the same data as Site administration > Reports > Logs, filtered to one student.';
$string['nocourseenrolledusers'] = 'This course has no enrolled users.';
$string['selectstudentlog'] = 'Student';
$string['downloadcsv'] = 'Download this student\'s CSV';
$string['downloadallcsv'] = 'Download all students\' CSV';
$string['loadinglogs'] = 'Loading navigation trail...';
$string['loadingalllogs'] = 'Preparing the CSV for every enrolled student...';
$string['nologdata'] = 'No log entries were found for this student in this course.';
$string['noalllogdata'] = 'No log entries were found for any enrolled student in this course.';
$string['logcolumn_time'] = 'Time';
$string['logcolumn_context'] = 'Context';
$string['logcolumn_component'] = 'Component';
$string['logcolumn_event'] = 'Event';
$string['logcolumn_student'] = 'Student';
$string['clickeventwithquestion'] = 'Selected "{$a->option}" on question {$a->number}';
$string['clickeventwithoutquestion'] = 'Selected "{$a}"';
$string['insight_essaybehavior_title'] = 'Writing behaviour in essay questions';
$string['insight_essaybehavior_desc'] = 'How students wrote their essay answers: time with the field focused, active typing time, pauses, words and pasted text. The text typed is never stored, only counts and sizes. The pasted share is an estimate (pasted characters relative to the final answer length) and is a signal to look into, not proof of copying.';
$string['noessayactivities'] = 'This course has no Quiz activities yet.';
$string['essayquestion'] = 'Question';
$string['loadingessay'] = 'Loading...';
$string['noessaydata'] = 'No essay writing has been recorded for this activity yet.';
$string['noessayevolution'] = 'No writing progress was recorded for this student on this question.';
$string['essaycompositiontitle'] = 'Typed vs pasted text (final answer)';
$string['essaythresholdnote'] = '"% pasted" colours: green below 20%, yellow from 20% to 49% and red from 50% (indicative thresholds).';
$string['essayviewevolution'] = 'View one student\'s writing evolution';
$string['essaycol_student'] = 'Student';
$string['essaycol_focus'] = 'Time focused';
$string['essaycol_typing'] = 'Time typing';
$string['essaycol_pauses'] = 'Pauses';
$string['essaycol_words'] = 'Words';
$string['essaycol_pastes'] = 'Pastes';
$string['essaycol_pastedchars'] = 'Pasted characters';
$string['essaycol_pastedpercent'] = '% pasted';
$string['essaytypedchars'] = 'Typed (estimated)';
$string['essaypastedchars'] = 'Pasted';
$string['essaycharsseries'] = 'Characters';
$string['essaywordsseries'] = 'Words';
$string['essaypasteseries'] = 'Paste';
$string['essaytypingevent'] = 'Typed for {$a->typing} on question {$a->number} ({$a->words} words, {$a->pauses} pauses)';
$string['essaytypingeventpasted'] = 'Typed for {$a->typing} on question {$a->number} ({$a->words} words, {$a->pauses} pauses, {$a->percent}% pasted)';
$string['essaypasteevent'] = 'Pasted {$a->chars} characters on question {$a->number}';
$string['avgminutestosubmit'] ='Avg. minutes to submit';
$string['copypasteevents'] = 'Copy/paste events';
$string['importedrows'] = 'Imported rows';
$string['avgnumericvalue'] = 'Avg. numeric value';
$string['clickcount'] = 'Clicks';
$string['rageburstcount'] = 'Rage-click bursts';
$string['submissionbucket_early3'] = 'More than 3 days early';
$string['submissionbucket_early1to3'] = '1-3 days early';
$string['submissionbucket_early6to24h'] = '6-24 hours early';
$string['submissionbucket_lastminute'] = 'Last few hours';
$string['submissionbucket_late'] = 'Late';

// Settings.
$string['pickerheading'] = 'Google Drive picker';
$string['pickerheading_desc'] = 'The CSV picker runs entirely in the browser (Google Identity Services + the Google Picker widget), so the Moodle server itself never needs outbound access to Google. See README.md for the exact Google Cloud Console steps.';
$string['googleclientid'] = 'Google OAuth 2.0 Client ID';
$string['googleclientid_desc'] = 'A "Web application" OAuth 2.0 Client ID from Google Cloud Console, with this site added under "Authorized JavaScript origins". This value is public and safe to expose in the browser.';
$string['pickerapikey'] = 'Google Picker API key';
$string['pickerapikey_desc'] = 'An API key from Google Cloud Console, restricted to the Picker API and to this site\'s HTTP referrer.';
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
$string['privacy:metadata:local_latracker_click'] = 'Mouse click samples, used for click-engagement and rage-click insights.';
$string['privacy:metadata:local_latracker_click:userid'] = 'The id of the user.';
$string['privacy:metadata:local_latracker_click:cmid'] = 'The activity (course module) id, if the click happened on an activity page.';
$string['privacy:metadata:local_latracker_click:pageurl'] = 'The URL of the page where the click happened.';
$string['privacy:metadata:local_latracker_click:targettag'] = 'The HTML tag name of the clicked element.';
$string['privacy:metadata:local_latracker_click:timecreatedms'] = 'When the click happened, in milliseconds.';
$string['privacy:metadata:local_latracker_click:optionlabel'] = 'Text of the answer/choice option selected, if the click resolved to one.';
$string['privacy:metadata:local_latracker_click:questioncontext'] = 'Short snippet identifying which quiz question the option belongs to.';
$string['privacy:metadata:local_latracker_submission'] = 'Actual submission time of an activity compared to its due date.';
$string['privacy:metadata:local_latracker_submission:userid'] = 'The id of the user.';
$string['privacy:metadata:local_latracker_submission:cmid'] = 'The activity (course module) id.';
$string['privacy:metadata:local_latracker_submission:duedate'] = 'The activity due date.';
$string['privacy:metadata:local_latracker_submission:timesubmitted'] = 'When the user actually submitted.';
$string['privacy:metadata:local_latracker_submission:secondsbeforedue'] = 'How many seconds before the due date the submission happened (negative if late).';
$string['privacy:metadata:local_latracker_essay'] = 'Writing behaviour in essay questions: focus and typing time, pauses, pastes and answer size. The text written is never stored.';
$string['privacy:metadata:local_latracker_essay:userid'] = 'The id of the user.';
$string['privacy:metadata:local_latracker_essay:cmid'] = 'The quiz (course module) id.';
$string['privacy:metadata:local_latracker_essay:questioncontext'] = 'Short snippet identifying the essay question.';
$string['privacy:metadata:local_latracker_essay:focusseconds'] = 'Seconds with the answer field focused.';
$string['privacy:metadata:local_latracker_essay:typingseconds'] = 'Seconds of active typing.';
$string['privacy:metadata:local_latracker_essay:keystrokes'] = 'Number of typing/deleting input events (not their content).';
$string['privacy:metadata:local_latracker_essay:pausecount'] = 'Number of pauses while typing.';
$string['privacy:metadata:local_latracker_essay:pastecount'] = 'Number of paste events.';
$string['privacy:metadata:local_latracker_essay:pastedchars'] = 'Total number of characters pasted (not the pasted text).';
$string['privacy:metadata:local_latracker_essay:finalchars'] = 'Answer length in characters.';
$string['privacy:metadata:local_latracker_essay:finalwords'] = 'Answer length in words.';
$string['privacy:metadata:local_latracker_essay:timeline'] = 'Answer size (characters and words) sampled over time.';
$string['privacy:metadata:local_latracker_essay:pastelog'] = 'When each paste happened and how many characters it had.';
