<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade steps for local_latracker.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_latracker_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026083000) {

        // Mouse click samples (click-engagement and rage-click insights).
        $table = new xmldb_table('local_latracker_click');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null);
            $table->add_field('pageurl', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null);
            $table->add_field('targettag', XMLDB_TYPE_CHAR, '40', null, null, null);
            $table->add_field('clientx', XMLDB_TYPE_INTEGER, '10', null, null, null);
            $table->add_field('clienty', XMLDB_TYPE_INTEGER, '10', null, null, null);
            $table->add_field('timecreatedms', XMLDB_TYPE_INTEGER, '16', null, XMLDB_NOTNULL, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

            $table->add_index('course_user_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'userid']);
            $table->add_index('user_cm_time_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'cmid', 'timecreatedms']);

            $dbman->create_table($table);
        }

        // Authoritative submission timing (from mod_assign/mod_quiz events).
        $table = new xmldb_table('local_latracker_submission');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
            $table->add_field('component', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null);
            $table->add_field('duedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
            $table->add_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
            $table->add_field('secondsbeforedue', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

            // No separate index on courseid alone: the courseid_fk key above
            // already indexes it, and an extra identical index collides.
            $table->add_index('user_cm_uidx', XMLDB_INDEX_UNIQUE, ['userid', 'cmid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026083000, 'local', 'latracker');
    }

    if ($oldversion < 2026090100) {

        // Which answer/choice option (if any) a click resolved to, and a
        // short snippet identifying the question it belongs to - used by
        // the "selected options" insight (mod_choice / mod_quiz clicks).
        $table = new xmldb_table('local_latracker_click');

        $field = new xmldb_field('optionlabel', XMLDB_TYPE_CHAR, '150', null, null, null, null, 'clienty');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('questioncontext', XMLDB_TYPE_CHAR, '150', null, null, null, null, 'optionlabel');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026090100, 'local', 'latracker');
    }

    if ($oldversion < 2026100500) {

        // Writing behaviour in essay questions (focus/typing time, pauses,
        // pastes, text size over time). Never stores the written text.
        $table = new xmldb_table('local_latracker_essay');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null);
            $table->add_field('usageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('slot', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sessionid', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null);
            $table->add_field('questionnumber', XMLDB_TYPE_CHAR, '20', null, null, null);
            $table->add_field('questioncontext', XMLDB_TYPE_CHAR, '150', null, null, null);
            $table->add_field('startedms', XMLDB_TYPE_INTEGER, '16', null, XMLDB_NOTNULL, null);
            $table->add_field('reportms', XMLDB_TYPE_INTEGER, '16', null, XMLDB_NOTNULL, null);
            $table->add_field('focusseconds', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('typingseconds', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('keystrokes', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('pausecount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('pastecount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('pastedchars', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('finalchars', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('finalwords', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timeline', XMLDB_TYPE_TEXT, null, null, null, null);
            $table->add_field('pastelog', XMLDB_TYPE_TEXT, null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

            // No index on a single FK column: the foreign keys above already
            // index them, and an identical index collides.
            $table->add_index('course_cm_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'cmid']);
            $table->add_index('session_uidx', XMLDB_INDEX_UNIQUE, ['userid', 'sessionid', 'usageid', 'slot']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100500, 'local', 'latracker');
    }

    return true;
}
