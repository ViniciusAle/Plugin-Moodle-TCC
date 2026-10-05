<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_latracker;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer that captures the authoritative "completion" time of any
 * activity, compared against its configured expected completion date.
 *
 * This listens to core's generic \core\event\course_module_completion_updated
 * event - fired for the "Mark as complete" checkbox on ANY activity type
 * (assignments, quizzes, forums, external tools/LTI, pages, ...), not just
 * gradable ones with their own notion of a due date. The "due date" used
 * for comparison is the activity's own "Expect completed on" setting
 * (cm_info::$completionexpected), which is why it is deliberately NOT
 * derived from module-specific fields like assign.duedate or
 * quiz.timeclose anymore: those don't exist for most activity types, and
 * "expected completion date" is the one deadline concept every activity
 * with completion tracking shares.
 *
 * This is deliberately NOT derived from the client-side tracker.js timer:
 * this event fires server-side whenever completion genuinely changes
 * (including via the mobile app, or when JS tracking failed to load for
 * any reason), which makes it the accurate source for the "did students
 * finish at the last minute?" insight.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * @param \core\event\course_module_completion_updated $event
     */
    public static function completion_updated(\core\event\course_module_completion_updated $event): void {
        global $DB;

        $completionstate = (int) ($event->other['completionstate'] ?? COMPLETION_INCOMPLETE);
        if ($completionstate === COMPLETION_INCOMPLETE) {
            // Only "became complete" transitions count as a completion
            // moment - a student unchecking the box isn't a submission.
            return;
        }

        $userid = $event->relateduserid;
        if (empty($userid)) {
            return;
        }

        $cmid = $event->contextinstanceid;

        try {
            [, $cm] = get_course_and_cm_from_cmid($cmid);
        } catch (\moodle_exception $e) {
            return;
        }

        $duedate = (int) $cm->completionexpected;
        if (!$duedate) {
            // No "Expect completed on" date configured for this activity -
            // nothing to compare the completion time against.
            return;
        }

        $timecompleted = $event->timecreated;

        $record = (object) [
            'userid'           => $userid,
            'courseid'         => $event->courseid,
            'cmid'             => $cmid,
            'component'        => 'mod_' . $cm->modname,
            'duedate'          => $duedate,
            'timesubmitted'    => $timecompleted,
            'secondsbeforedue' => $duedate - $timecompleted,
            'timecreated'      => time(),
        ];

        // Only the most recent completion per user/activity is kept, since
        // that is the one that reflects whether they ultimately left it to
        // the last minute.
        $existing = $DB->get_record('local_latracker_submission', ['userid' => $userid, 'cmid' => $cmid]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_latracker_submission', $record);
        } else {
            $DB->insert_record('local_latracker_submission', $record);
        }
    }
}
