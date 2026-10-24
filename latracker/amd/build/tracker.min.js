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
 *    selected, for the "selected options" insight; and
 *  - writing behaviour in quiz essay questions (focus/typing time, pauses,
 *    pastes via the browser 'paste' event, text size over time), for the
 *    "essay writing behaviour" insight. Only counts and text SIZES are
 *    reported - never the text itself.
 *
 * All writes go through the local_latracker_track_event,
 * local_latracker_track_clicks and local_latracker_track_essay web
 * services (db/services.php), which are
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

    /** A gap between two typing inputs at or above this counts as a "pause" (and is not typing time). */
    var ESSAY_PAUSE_MS = 5000;

    /** Minimum interval between text-size snapshots while typing. */
    var ESSAY_SNAPSHOT_MS = 10000;

    /** Snapshots/pastes kept per field; the server enforces its own caps too. */
    var ESSAY_MAX_POINTS = 150;
    var ESSAY_MAX_PASTES = 100;

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
            // Time on another tab is not time with the field focused.
            closeAllEssayFocus();
            flushEssays(true);
            state.visible = false;
        } else {
            state.pageStart = nowSeconds();
            state.visible = true;
            resumeEssayFocus();
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

    // ---------------------------------------------------------------------
    // Essay question writing behaviour.
    //
    // Works with the three editors an essay answer can use:
    //  - a plain <textarea> (plain text / monospaced formats);
    //  - Atto, whose editing area is a contenteditable <div> in the SAME
    //    document, so events bubble up to the question block; and
    //  - TinyMCE (the Moodle 4.5 default), whose editing area is an <iframe>:
    //    keyboard/paste events inside it never reach this document, so each
    //    iframe's own document is bound separately, once it is ready.
    // ---------------------------------------------------------------------

    /** Every registered essay field on the page. */
    var essayFields = [];

    /** Random id of this page visit, so reports of the same visit upsert one row. */
    var essaySessionId = '';

    var randomId = function() {
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var id = '';
        for (var i = 0; i < 24; i++) {
            id += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return id;
    };

    /** Collapses whitespace runs (and non-breaking/zero-width spaces) so sizes are stable across editors. */
    var normalizeText = function(text) {
        return (text || '').replace(/[​ ]/g, ' ').replace(/\s+/g, ' ').trim();
    };

    var countWords = function(normalized) {
        return normalized === '' ? 0 : normalized.split(' ').length;
    };

    /** Reads the current answer text from whichever editor the field uses. */
    var readEssayText = function(field) {
        var frames = field.que.querySelectorAll('iframe');
        for (var i = 0; i < frames.length; i++) {
            try {
                var doc = frames[i].contentDocument;
                if (doc && doc.body && doc.body.isContentEditable) {
                    return doc.body.innerText || doc.body.textContent || '';
                }
            } catch (e) {
                // Not a same-origin frame: not an editor.
            }
        }

        var atto = field.que.querySelector('.editor_atto_content');
        if (atto) {
            return atto.innerText || atto.textContent || '';
        }

        var textarea = field.que.querySelector('textarea');
        return textarea ? textarea.value : '';
    };

    var isEditable = function(element) {
        return !!element && element.nodeType === 1
            && (element.tagName === 'TEXTAREA' || element.isContentEditable === true);
    };

    /** Records the answer's current size, unless it hasn't changed since the last snapshot. */
    var takeEssaySnapshot = function(field, now) {
        var text = normalizeText(readEssayText(field));
        var chars = text.length;
        var words = countWords(text);
        var last = field.timeline[field.timeline.length - 1];

        field.lastSnapshotMs = now;
        if (last && last[1] === chars && last[2] === words) {
            return;
        }

        field.timeline.push([Math.round((now - field.startedms) / 1000), chars, words]);
        if (field.timeline.length > ESSAY_MAX_POINTS) {
            // Thin out: drop every second interior point, keeping first and last.
            var total = field.timeline.length;
            field.timeline = field.timeline.filter(function(point, index) {
                return index === 0 || index === total - 1 || index % 2 === 0;
            });
        }
        field.dirty = true;
    };

    var startEssayFocus = function(field) {
        if (!field.focusStart && state.visible) {
            field.focusStart = Date.now();
        }
    };

    var closeEssayFocus = function(field) {
        if (field.focusStart) {
            field.focusMs += Date.now() - field.focusStart;
            field.focusStart = 0;
            field.dirty = true;
        }
    };

    var closeAllEssayFocus = function() {
        essayFields.forEach(closeEssayFocus);
    };

    /** After returning to the tab, resumes focus time for a field that still has the caret. */
    var resumeEssayFocus = function() {
        essayFields.forEach(function(field) {
            var active = document.activeElement;
            if (active && field.que.contains(active)) {
                startEssayFocus(field);
            }
        });
    };

    var onEssayFocus = function(field, event) {
        if (!isEditable(event.target)) {
            return;
        }
        startEssayFocus(field);
        if (!field.timeline.length) {
            // Baseline: the size the answer already had before this visit's edits.
            takeEssaySnapshot(field, Date.now());
        }
    };

    var onEssayBlur = function(field, event) {
        if (isEditable(event.target)) {
            closeEssayFocus(field);
        }
    };

    var onEssayInput = function(field, event) {
        if (!isEditable(event.target)) {
            return;
        }

        var now = Date.now();
        field.dirty = true;
        startEssayFocus(field);

        // Pastes/drops are counted by the 'paste' handler, not as typing.
        if (/^(insertFromPaste|insertFromDrop|insertFromYank|insertReplacementText)/.test(event.inputType || '')) {
            field.lastInputMs = now;
            takeEssaySnapshot(field, now);
            return;
        }

        field.keystrokes++;
        if (field.lastInputMs) {
            var gap = now - field.lastInputMs;
            if (gap >= ESSAY_PAUSE_MS) {
                field.pauses++;
            } else {
                field.typingMs += gap;
            }
        }
        field.lastInputMs = now;

        if (now - field.lastSnapshotMs >= ESSAY_SNAPSHOT_MS) {
            takeEssaySnapshot(field, now);
        }
    };

    /**
     * Uses the browser 'paste' event (not the Ctrl+V key), so pastes via
     * the context menu, the browser menu or a touch keyboard count too.
     * Only the pasted text's SIZE is kept.
     */
    var onEssayPaste = function(field, event) {
        if (!isEditable(event.target)) {
            return;
        }

        var clipboard = event.clipboardData || window.clipboardData;
        var text = clipboard && clipboard.getData ? clipboard.getData('text') : '';
        var chars = normalizeText(text).length;
        if (!chars) {
            return;
        }

        var now = Date.now();
        field.pasteCount++;
        field.pastedChars += chars;
        if (field.pastes.length < ESSAY_MAX_PASTES) {
            field.pastes.push([Math.round((now - field.startedms) / 1000), chars]);
        }
        field.lastInputMs = now;
        field.dirty = true;
        startEssayFocus(field);

        // The pasted text is only in the editor after this event finishes.
        window.setTimeout(function() {
            takeEssaySnapshot(field, Date.now());
        }, 0);
    };

    var bindEssayEvents = function(field, target) {
        target.addEventListener('focusin', function(event) {
            onEssayFocus(field, event);
        }, true);
        target.addEventListener('focusout', function(event) {
            onEssayBlur(field, event);
        }, true);
        target.addEventListener('input', function(event) {
            onEssayInput(field, event);
        }, true);
        target.addEventListener('paste', function(event) {
            onEssayPaste(field, event);
        }, true);
    };

    /**
     * Binds the document inside every editor iframe (TinyMCE) found in an
     * essay field. TinyMCE creates its iframe after page load and may swap
     * its document, so this runs repeatedly and remembers which documents
     * it has already bound.
     */
    var bindEssayFrames = function() {
        essayFields.forEach(function(field) {
            field.que.querySelectorAll('iframe').forEach(function(frame) {
                var doc = null;
                try {
                    doc = frame.contentDocument;
                } catch (e) {
                    return;
                }
                if (!doc || !doc.body || !doc.body.isContentEditable || field.boundDocs.indexOf(doc) !== -1) {
                    return;
                }
                field.boundDocs.push(doc);
                bindEssayEvents(field, doc);
            });
        });
    };

    var registerEssay = function(que, index) {
        // Review pages render the answer read-only (no editable surface).
        if (que.__latrackerEssay || !que.querySelector('textarea:not([readonly]):not([disabled]), .editor_atto_content')) {
            return;
        }

        // Quiz question blocks have id "question-<usageid>-<slot>": the
        // usage id identifies the attempt, the slot is the same for every
        // student, which is what lets answers be compared across students.
        var idMatch = /^question-(\d+)-(\d+)$/.exec(que.id || '');
        var number = que.querySelector('.info .no .qno');
        var qtext = que.querySelector('.qtext');

        var field = {
            que: que,
            usageid: idMatch ? parseInt(idMatch[1], 10) : 0,
            slot: idMatch ? parseInt(idMatch[2], 10) : index + 1,
            questionnumber: number ? number.textContent.trim().slice(0, 20) : String(index + 1),
            questioncontext: qtext ? normalizeText(qtext.textContent).slice(0, 150) : '',
            startedms: Date.now(),
            focusMs: 0,
            focusStart: 0,
            typingMs: 0,
            lastInputMs: 0,
            keystrokes: 0,
            pauses: 0,
            pasteCount: 0,
            pastedChars: 0,
            pastes: [],
            timeline: [],
            lastSnapshotMs: 0,
            dirty: false,
            boundDocs: []
        };

        que.__latrackerEssay = field;
        essayFields.push(field);
        // Plain textarea and Atto: events bubble to the question block.
        bindEssayEvents(field, que);
    };

    var buildEssayPayload = function(field, now) {
        // Refresh the final size so the report reflects the latest text.
        takeEssaySnapshot(field, now);
        var text = normalizeText(readEssayText(field));
        var focusMs = field.focusMs + (field.focusStart ? now - field.focusStart : 0);

        return {
            sessionid: essaySessionId,
            usageid: field.usageid,
            slot: field.slot,
            cmid: state.cmid,
            questionnumber: field.questionnumber,
            questioncontext: field.questioncontext,
            startedms: field.startedms,
            reportms: now,
            focusseconds: Math.round(focusMs / 1000),
            typingseconds: Math.round(field.typingMs / 1000),
            keystrokes: field.keystrokes,
            pausecount: field.pauses,
            pastecount: field.pasteCount,
            pastedchars: field.pastedChars,
            finalchars: text.length,
            finalwords: countWords(text),
            timeline: JSON.stringify(field.timeline),
            pastelog: JSON.stringify(field.pastes)
        };
    };

    /**
     * Sends the cumulative metrics of every essay field that changed since
     * the last report (each report replaces the previous one server-side).
     */
    var flushEssays = function(useBeacon) {
        var now = Date.now();
        var payloads = [];
        var reported = [];

        essayFields.forEach(function(field) {
            var touched = field.keystrokes || field.pasteCount || field.focusMs || field.focusStart;
            if (!field.dirty || !touched) {
                return;
            }
            payloads.push(buildEssayPayload(field, now));
            reported.push(field);
        });

        if (!payloads.length) {
            return;
        }
        reported.forEach(function(field) {
            field.dirty = false;
        });

        var args = {courseid: state.courseid, fields: payloads};
        if (useBeacon) {
            if (!sendBeaconCall('local_latracker_track_essay', args)) {
                sendCall('local_latracker_track_essay', args);
            }
        } else {
            sendCall('local_latracker_track_essay', args);
        }
    };

    var bindEssayTracking = function() {
        var blocks = document.querySelectorAll('.que.essay');
        if (!blocks.length) {
            return;
        }

        essaySessionId = randomId();
        blocks.forEach(registerEssay);
        if (!essayFields.length) {
            return;
        }

        bindEssayFrames();
        window.setInterval(bindEssayFrames, 2000);
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
                closeAllEssayFocus();
                flushEssays(true);
            });
            document.addEventListener('keydown', handleKeydown, true);
            document.addEventListener('click', handleClick, true);
            document.addEventListener('change', handleSelectChange, true);

            window.setInterval(function() {
                if (state.visible) {
                    flushPageTime(false);
                    flushClicks(false);
                    flushEssays(false);
                }
            }, HEARTBEAT_SECONDS * 1000);

            bindActivityLifecycle();
            bindEssayTracking();
        }
    };
});
