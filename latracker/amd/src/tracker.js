// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Student-facing tracking engine.
 *
 * Injected on every course page (see local_latracker_before_footer() in
 * lib.php) for users who hold local/latracker:track. Reports, entirely
 * asynchronously so it never blocks page rendering:
 *  - time spent on the current page (heartbeat + Page Visibility API);
 *  - accumulated platform time per course (aggregated server-side from
 *    the page-view reports, see track_event::accumulate_session_time());
 *  - time between opening an activity and submitting its form; and
 *  - Ctrl+C / Ctrl+V usage while navigating/resolving an activity.
 *
 * All writes go through the local_latracker_track_event web service
 * (db/services.php), which is CSRF-protected by Moodle's AJAX dispatcher
 * (mandatory sesskey check) and by a server-side capability check.
 *
 * @module      local_latracker/tracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    /** Seconds between periodic "still on this page" reports. */
    var HEARTBEAT_SECONDS = 30;

    var state = {
        courseid: 0,
        cmid: 0,
        pageurl: '',
        pageStart: 0,
        visible: true
    };

    var nowSeconds = function() {
        return Math.floor(Date.now() / 1000);
    };

    var buildArgs = function(eventtype, extra) {
        return Object.assign({
            courseid: state.courseid,
            cmid: state.cmid,
            eventtype: eventtype,
            pageurl: state.pageurl,
            duration: 0
        }, extra || {});
    };

    /**
     * Sends an event through the normal (non-blocking) AJAX channel.
     */
    var sendEvent = function(eventtype, extra) {
        return Ajax.call([{
            methodname: 'local_latracker_track_event',
            args: buildArgs(eventtype, extra)
        }])[0].catch(function() {
            // Tracking must never surface an error to the student.
        });
    };

    /**
     * Sends an event via sendBeacon, for moments where the page is being
     * unloaded/hidden and a normal async XHR could be aborted by the
     * browser before it completes.
     */
    var sendEventBeacon = function(eventtype, extra) {
        if (!navigator.sendBeacon || typeof M === 'undefined' || !M.cfg) {
            sendEvent(eventtype, extra);
            return;
        }

        var payload = [{
            index: 0,
            methodname: 'local_latracker_track_event',
            args: buildArgs(eventtype, extra)
        }];
        var url = M.cfg.wwwroot + '/lib/ajax/service.php'
            + '?sesskey=' + encodeURIComponent(M.cfg.sesskey)
            + '&info=local_latracker_track_event';
        var blob = new Blob([JSON.stringify(payload)], {type: 'application/json'});

        navigator.sendBeacon(url, blob);
    };

    /**
     * Reports the time elapsed since the page became visible/loaded and
     * resets the counter.
     */
    var flushPageTime = function(useBeacon) {
        var elapsed = nowSeconds() - state.pageStart;
        if (elapsed <= 0) {
            return;
        }
        if (useBeacon) {
            sendEventBeacon('pageview', {duration: elapsed});
        } else {
            sendEvent('pageview', {duration: elapsed});
        }
        state.pageStart = nowSeconds();
    };

    var handleVisibilityChange = function() {
        if (document.visibilityState === 'hidden') {
            flushPageTime(true);
            state.visible = false;
        } else {
            state.pageStart = nowSeconds();
            state.visible = true;
        }
    };

    var handleKeydown = function(event) {
        var isModifierCombo = event.ctrlKey || event.metaKey;
        if (!isModifierCombo) {
            return;
        }
        var key = (event.key || '').toLowerCase();
        if (key === 'c') {
            sendEvent('copy');
        } else if (key === 'v') {
            sendEvent('paste');
        }
    };

    /**
     * Starts the open->submit timer for the current activity, and wires
     * every form on the page so submitting it stops the timer.
     */
    var bindActivityLifecycle = function() {
        if (!state.cmid) {
            return;
        }

        sendEvent('activitystart');

        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function() {
                sendEventBeacon('activitysubmit');
            });
        });
    };

    return {
        /**
         * @param {Object} config
         * @param {Number} config.courseid
         * @param {Number} config.cmid 0 when the page is not an activity.
         * @param {String} config.pageurl
         */
        init: function(config) {
            state.courseid = config.courseid || 0;
            state.cmid = config.cmid || 0;
            state.pageurl = config.pageurl || window.location.href;
            state.pageStart = nowSeconds();

            document.addEventListener('visibilitychange', handleVisibilityChange);
            window.addEventListener('pagehide', function() {
                flushPageTime(true);
            });
            document.addEventListener('keydown', handleKeydown, true);

            window.setInterval(function() {
                if (state.visible) {
                    flushPageTime(false);
                }
            }, HEARTBEAT_SECONDS * 1000);

            bindActivityLifecycle();
        }
    };
});
