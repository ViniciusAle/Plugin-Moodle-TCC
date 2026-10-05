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
 *  - time between opening an activity and submitting its form;
 *  - Ctrl+C / Ctrl+V usage while navigating/resolving an activity; and
 *  - mouse clicks, batched and flushed periodically (used server-side for
 *    click-engagement and rage-click insights); clicks on a quiz question
 *    or Choice activity option also resolve which option's text was
 *    selected, for the "selected options" insight.
 *
 * All writes go through the local_latracker_track_event and
 * local_latracker_track_clicks web services (db/services.php), which are
 * CSRF-protected by Moodle's AJAX dispatcher (mandatory sesskey check)
 * and by a server-side capability check.
 *
 * @module      local_latracker/tracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    /** Seconds between periodic "still on this page" reports. */
    var HEARTBEAT_SECONDS = 30;

    /** Flush the click buffer early if it grows past this size. */
    var CLICK_FLUSH_THRESHOLD = 25;

    var state = {
        courseid: 0,
        cmid: 0,
        pageurl: '',
        pageStart: 0,
        visible: true
    };

    var clickBuffer = [];

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
     * Sends a call through the normal (non-blocking) AJAX channel.
     */
    var sendCall = function(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0].catch(function() {
            // Tracking must never surface an error to the student.
        });
    };

    /**
     * Sends a call via sendBeacon, for moments where the page is being
     * unloaded/hidden and a normal async XHR could be aborted by the
     * browser before it completes. Returns false if sendBeacon isn't
     * available (or the M.cfg globals aren't), so the caller can fall
     * back to the normal channel.
     */
    var sendBeaconCall = function(methodname, args) {
        if (!navigator.sendBeacon || typeof M === 'undefined' || !M.cfg) {
            return false;
        }

        var payload = [{index: 0, methodname: methodname, args: args}];
        var url = M.cfg.wwwroot + '/lib/ajax/service.php'
            + '?sesskey=' + encodeURIComponent(M.cfg.sesskey)
            + '&info=' + methodname;
        var blob = new Blob([JSON.stringify(payload)], {type: 'application/json'});

        return navigator.sendBeacon(url, blob);
    };

    var sendEvent = function(eventtype, extra) {
        return sendCall('local_latracker_track_event', buildArgs(eventtype, extra));
    };

    var sendEventBeacon = function(eventtype, extra) {
        var args = buildArgs(eventtype, extra);
        if (!sendBeaconCall('local_latracker_track_event', args)) {
            sendCall('local_latracker_track_event', args);
        }
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

    /**
     * Sends the buffered clicks (if any) and empties the buffer.
     */
    var flushClicks = function(useBeacon) {
        if (!clickBuffer.length) {
            return;
        }
        var clicks = clickBuffer;
        clickBuffer = [];
        var args = {courseid: state.courseid, clicks: clicks};

        if (useBeacon) {
            if (!sendBeaconCall('local_latracker_track_clicks', args)) {
                sendCall('local_latracker_track_clicks', args);
            }
        } else {
            sendCall('local_latracker_track_clicks', args);
        }
    };

    var handleVisibilityChange = function() {
        if (document.visibilityState === 'hidden') {
            flushPageTime(true);
            flushClicks(true);
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
     * If a click landed on (or inside) an answer/choice option, resolves
     * the option's visible text, and, for quiz questions, a short snippet
     * of the question text too. Returns null for clicks that aren't on a
     * recognisable option, which is the common case.
     *
     * Two different markup patterns are handled:
     * - mod_choice renders a real <label for="..."> for each option.
     * - Moodle's quiz question engine (qtype_multichoice/truefalse) does
     *   NOT use a <label> element at all - the option text lives in a
     *   <div data-region="answer-label"> referenced by the input's
     *   aria-labelledby attribute instead (its own AMD module comments
     *   "these cannot be enclosed in a label element"). Missing this
     *   pattern is why quiz option clicks previously went unrecorded.
     *
     * @param {Element} target
     * @return {?{optionlabel: String, questioncontext: ?String}}
     */
    var resolveClickedOption = function(target) {
        if (!target || !target.closest) {
            return null;
        }

        var questionEl = target.closest('.que');
        var choiceEl = target.closest('#choiceform, .choices, form[action*="/mod/choice/view.php"]');
        if (!questionEl && !choiceEl) {
            return null;
        }

        var label = target.closest('[data-region="answer-label"]');

        if (!label) {
            var input = target.closest('input[type="radio"], input[type="checkbox"]');
            if (input) {
                var labelledby = input.getAttribute('aria-labelledby');
                if (labelledby) {
                    label = document.getElementById(labelledby);
                }
                if (!label && input.id && window.CSS && CSS.escape) {
                    label = document.querySelector('label[for="' + CSS.escape(input.id) + '"]');
                }
                if (!label) {
                    label = input.closest('label');
                }
            } else if (target.tagName === 'LABEL') {
                label = target;
            }
        }

        var optionlabel = label ? label.textContent.replace(/\s+/g, ' ').trim() : '';
        if (!optionlabel) {
            return null;
        }

        var questioncontext = '';
        if (questionEl) {
            var qtext = questionEl.querySelector('.qtext');
            if (qtext) {
                questioncontext = qtext.textContent.replace(/\s+/g, ' ').trim().slice(0, 150);
            }
        }

        return {optionlabel: optionlabel.slice(0, 150), questioncontext: questioncontext};
    };

    var pushClick = function(target, extra) {
        clickBuffer.push(Object.assign({
            cmid: state.cmid,
            pageurl: state.pageurl,
            targettag: (target && target.tagName) ? target.tagName : '',
            x: 0,
            y: 0,
            optionlabel: '',
            questioncontext: '',
            timestampms: Date.now()
        }, extra || {}));

        if (clickBuffer.length >= CLICK_FLUSH_THRESHOLD) {
            flushClicks(false);
        }
    };

    var handleClick = function(event) {
        // Some Moodle UI (e.g. qtype_multichoice/answers.js) relays a real
        // click on the visible answer text into a synthetic input.click()
        // so the radio/checkbox actually gets selected. That synthetic
        // click is not event.isTrusted, and would otherwise be recorded as
        // a second, redundant click for the same user action.
        if (event.isTrusted === false) {
            return;
        }

        var target = event.target;
        var option = resolveClickedOption(target);

        pushClick(target, Object.assign({
            x: Math.round(event.clientX || 0),
            y: Math.round(event.clientY || 0)
        }, option || {}));
    };

    /**
     * Choice activities can also be displayed as a <select> dropdown
     * instead of radio buttons - picking an option there never fires a
     * 'click' on the option itself (native dropdown lists aren't part of
     * the DOM the page can see), so it needs its own 'change' listener.
     */
    var handleSelectChange = function(event) {
        var select = event.target;
        if (!select || select.tagName !== 'SELECT' || !select.closest) {
            return;
        }
        if (!select.closest('#choiceform, .choices, form[action*="/mod/choice/view.php"]')) {
            return;
        }

        var selected = select.options[select.selectedIndex];
        if (!selected || !selected.value) {
            return;
        }

        pushClick(select, {
            optionlabel: selected.text.replace(/\s+/g, ' ').trim().slice(0, 150)
        });
    };

    /**
     * Ensures HTMLFormElement.prototype.submit() also reports the event,
     * not just a real 'submit' DOM event.
     *
     * Some Moodle activities (LTI launches in particular) submit their
     * form programmatically via form.submit() to redirect to an external
     * tool almost immediately after the page loads. Per the DOM spec,
     * calling that method directly does NOT dispatch a 'submit' event, so
     * a plain addEventListener('submit', ...) never sees it - this patch
     * is what makes activitysubmit tracking work for those pages too.
     */
    var patchProgrammaticSubmit = (function() {
        var patched = false;
        return function(onSubmit) {
            if (patched || typeof HTMLFormElement === 'undefined') {
                return;
            }
            patched = true;
            var nativeSubmit = HTMLFormElement.prototype.submit;
            HTMLFormElement.prototype.submit = function() {
                onSubmit();
                return nativeSubmit.apply(this, arguments);
            };
        };
    })();

    /**
     * Starts the open->submit timer for the current activity, and wires
     * every form on the page so submitting it stops the timer.
     */
    var bindActivityLifecycle = function() {
        if (!state.cmid) {
            return;
        }

        // Sent via beacon (not the normal async AJAX channel) because some
        // activities (LTI launches) navigate away within milliseconds of
        // load, which would otherwise cancel a still-pending XHR before it
        // reaches the server.
        sendEventBeacon('activitystart');

        var onSubmit = function() {
            sendEventBeacon('activitysubmit');
        };

        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', onSubmit);
        });
        patchProgrammaticSubmit(onSubmit);
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
                flushClicks(true);
            });
            document.addEventListener('keydown', handleKeydown, true);
            document.addEventListener('click', handleClick, true);
            document.addEventListener('change', handleSelectChange, true);

            window.setInterval(function() {
                if (state.visible) {
                    flushPageTime(false);
                    flushClicks(false);
                }
            }, HEARTBEAT_SECONDS * 1000);

            bindActivityLifecycle();
        }
    };
});
