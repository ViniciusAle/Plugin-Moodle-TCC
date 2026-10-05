<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_latracker\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_course;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_latracker.
 *
 * Every tracker table keyed by userid lives at course context level, since
 * a student's tracked events (page time, activity timers, copy/paste,
 * clicks, submission timing) are always scoped to a single course.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** @var string[] Every course-scoped, userid-keyed tracker table. */
    const TABLES = [
        'local_latracker_pageview',
        'local_latracker_sessiontime',
        'local_latracker_activitytime',
        'local_latracker_event',
        'local_latracker_click',
        'local_latracker_submission',
    ];

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_latracker_pageview', [
            'userid'      => 'privacy:metadata:local_latracker_pageview:userid',
            'courseid'    => 'privacy:metadata:local_latracker_pageview:courseid',
            'pageurl'     => 'privacy:metadata:local_latracker_pageview:pageurl',
            'duration'    => 'privacy:metadata:local_latracker_pageview:duration',
            'timecreated' => 'privacy:metadata:local_latracker_pageview:timecreated',
        ], 'privacy:metadata:local_latracker_pageview');

        $collection->add_database_table('local_latracker_sessiontime', [
            'userid'    => 'privacy:metadata:local_latracker_sessiontime:userid',
            'courseid'  => 'privacy:metadata:local_latracker_sessiontime:courseid',
            'totaltime' => 'privacy:metadata:local_latracker_sessiontime:totaltime',
        ], 'privacy:metadata:local_latracker_sessiontime');

        $collection->add_database_table('local_latracker_activitytime', [
            'userid'        => 'privacy:metadata:local_latracker_activitytime:userid',
            'cmid'          => 'privacy:metadata:local_latracker_activitytime:cmid',
            'timestarted'   => 'privacy:metadata:local_latracker_activitytime:timestarted',
            'timesubmitted' => 'privacy:metadata:local_latracker_activitytime:timesubmitted',
        ], 'privacy:metadata:local_latracker_activitytime');

        $collection->add_database_table('local_latracker_event', [
            'userid'    => 'privacy:metadata:local_latracker_event:userid',
            'eventtype' => 'privacy:metadata:local_latracker_event:eventtype',
            'pageurl'   => 'privacy:metadata:local_latracker_event:pageurl',
        ], 'privacy:metadata:local_latracker_event');

        $collection->add_database_table('local_latracker_click', [
            'userid'        => 'privacy:metadata:local_latracker_click:userid',
            'cmid'          => 'privacy:metadata:local_latracker_click:cmid',
            'pageurl'       => 'privacy:metadata:local_latracker_click:pageurl',
            'targettag'     => 'privacy:metadata:local_latracker_click:targettag',
            'optionlabel'   => 'privacy:metadata:local_latracker_click:optionlabel',
            'questioncontext' => 'privacy:metadata:local_latracker_click:questioncontext',
            'timecreatedms' => 'privacy:metadata:local_latracker_click:timecreatedms',
        ], 'privacy:metadata:local_latracker_click');

        $collection->add_database_table('local_latracker_submission', [
            'userid'           => 'privacy:metadata:local_latracker_submission:userid',
            'cmid'             => 'privacy:metadata:local_latracker_submission:cmid',
            'duedate'          => 'privacy:metadata:local_latracker_submission:duedate',
            'timesubmitted'    => 'privacy:metadata:local_latracker_submission:timesubmitted',
            'secondsbeforedue' => 'privacy:metadata:local_latracker_submission:secondsbeforedue',
        ], 'privacy:metadata:local_latracker_submission');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $unions = [];
        $params = [];
        foreach (self::TABLES as $i => $table) {
            $unions[] = "SELECT courseid FROM {{$table}} WHERE userid = :uid{$i}";
            $params["uid{$i}"] = $userid;
        }
        $params['contextlevel'] = CONTEXT_COURSE;

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN (" . implode(' UNION ', $unions) . ") tracked ON tracked.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel";

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $courseid = $context->instanceid;

        foreach (self::TABLES as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}} WHERE courseid = :courseid",
                ['courseid' => $courseid]);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $courseid = $context->instanceid;
            $userid = $contextlist->get_user()->id;

            $data = (object) [
                'pageviews'    => array_values($DB->get_records('local_latracker_pageview', ['courseid' => $courseid, 'userid' => $userid])),
                'sessiontime'  => $DB->get_record('local_latracker_sessiontime', ['courseid' => $courseid, 'userid' => $userid]) ?: null,
                'activitytime' => array_values($DB->get_records('local_latracker_activitytime', ['courseid' => $courseid, 'userid' => $userid])),
                'events'       => array_values($DB->get_records('local_latracker_event', ['courseid' => $courseid, 'userid' => $userid])),
                'clicks'       => array_values($DB->get_records('local_latracker_click', ['courseid' => $courseid, 'userid' => $userid])),
                'submissions'  => array_values($DB->get_records('local_latracker_submission', ['courseid' => $courseid, 'userid' => $userid])),
            ];

            writer::with_context($context)->export_data([get_string('pluginname', 'local_latracker')], $data);
        }
    }

    public static function delete_data_for_all_users_in_context(context $context): void {
        if (!$context instanceof context_course) {
            return;
        }
        self::delete_course_data($context->instanceid);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $courseid = $context->instanceid;
            foreach (self::TABLES as $table) {
                $DB->delete_records($table, ['courseid' => $courseid, 'userid' => $userid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $courseid = $context->instanceid;

        foreach ($userlist->get_userids() as $userid) {
            foreach (self::TABLES as $table) {
                $DB->delete_records($table, ['courseid' => $courseid, 'userid' => $userid]);
            }
        }
    }

    private static function delete_course_data(int $courseid): void {
        global $DB;
        foreach (self::TABLES as $table) {
            $DB->delete_records($table, ['courseid' => $courseid]);
        }
    }
}
