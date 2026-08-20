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
 * a student's tracked events (page time, activity timers, copy/paste) are
 * always scoped to a single course.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

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

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN (
                        SELECT courseid FROM {local_latracker_pageview} WHERE userid = :uid1
                        UNION SELECT courseid FROM {local_latracker_sessiontime} WHERE userid = :uid2
                        UNION SELECT courseid FROM {local_latracker_activitytime} WHERE userid = :uid3
                        UNION SELECT courseid FROM {local_latracker_event} WHERE userid = :uid4
                       ) tracked ON tracked.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel";

        $contextlist->add_from_sql($sql, [
            'uid1' => $userid, 'uid2' => $userid, 'uid3' => $userid, 'uid4' => $userid,
            'contextlevel' => CONTEXT_COURSE,
        ]);

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $courseid = $context->instanceid;

        foreach (['local_latracker_pageview', 'local_latracker_sessiontime',
                  'local_latracker_activitytime', 'local_latracker_event'] as $table) {
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
            $DB->delete_records('local_latracker_pageview', ['courseid' => $courseid, 'userid' => $userid]);
            $DB->delete_records('local_latracker_sessiontime', ['courseid' => $courseid, 'userid' => $userid]);
            $DB->delete_records('local_latracker_activitytime', ['courseid' => $courseid, 'userid' => $userid]);
            $DB->delete_records('local_latracker_event', ['courseid' => $courseid, 'userid' => $userid]);
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
            $DB->delete_records('local_latracker_pageview', ['courseid' => $courseid, 'userid' => $userid]);
            $DB->delete_records('local_latracker_sessiontime', ['courseid' => $courseid, 'userid' => $userid]);
            $DB->delete_records('local_latracker_activitytime', ['courseid' => $courseid, 'userid' => $userid]);
            $DB->delete_records('local_latracker_event', ['courseid' => $courseid, 'userid' => $userid]);
        }
    }

    private static function delete_course_data(int $courseid): void {
        global $DB;
        $DB->delete_records('local_latracker_pageview', ['courseid' => $courseid]);
        $DB->delete_records('local_latracker_sessiontime', ['courseid' => $courseid]);
        $DB->delete_records('local_latracker_activitytime', ['courseid' => $courseid]);
        $DB->delete_records('local_latracker_event', ['courseid' => $courseid]);
    }
}
