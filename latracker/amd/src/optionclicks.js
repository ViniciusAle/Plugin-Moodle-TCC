// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * "Selected options" chart on analytics.php: the teacher picks a quiz or
 * Choice activity from a dropdown, and sees how many times each answer
 * option was clicked, with a second dropdown to filter which students
 * count towards the totals - plus a third dropdown to inspect one
 * student's full click sequence for that activity (e.g. "clicked A, then
 * B, then A again on submit").
 *
 * The activity dropdown triggers one AJAX call (local_latracker_get_option_clicks)
 * that returns every recorded click for that activity; the student filter
 * and the sequence view both just re-read that same data client-side, no
 * further requests needed when either changes.
 *
 * @module      local_latracker/optionclicks
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str', 'core/chartjs'], function(Ajax, Str, Chartjs) {

    var courseId = 0;
    var root = null;
    var charts = [];
    var currentData = null;
    var selectedStudents = null;

    var showStatus = function(message) {
        root.querySelector('[data-region="status-message"]').textContent = message || '';
    };

    var aggregateCounts = function(data, selected) {
        var counts = data.options.map(function() {
            return 0;
        });
        data.events.forEach(function(event) {
            if (selected.has(event.studentid)) {
                counts[event.optionindex]++;
            }
        });
        return counts;
    };

    /**
     * Groups option indices by the question they belong to (insertion
     * order = first-seen order), so each question gets its own small
     * chart instead of one long list mixing every question together.
     * Activities with no question context (Choice) end up as a single
     * group with an empty key, rendered without a heading.
     */
    var groupOptionsByQuestion = function(options) {
        var groups = new Map();
        options.forEach(function(option, index) {
            var key = option.question || '';
            if (!groups.has(key)) {
                groups.set(key, []);
            }
            groups.get(key).push(index);
        });
        return groups;
    };

    /**
     * Renders one bar chart per question into the charts container,
     * destroying whatever was there before.
     */
    var renderCharts = function(data, selected) {
        var container = root.querySelector('[data-region="charts-container"]');
        container.innerHTML = '';
        charts.forEach(function(existing) {
            existing.destroy();
        });
        charts = [];

        var counts = aggregateCounts(data, selected);
        var groups = groupOptionsByQuestion(data.options);

        groups.forEach(function(indices, question) {
            var block = document.createElement('div');
            block.className = 'local-latracker-option-question mb-4';

            if (question) {
                var heading = document.createElement('div');
                heading.className = 'font-weight-bold small mb-2';
                heading.textContent = question;
                block.appendChild(heading);
            }

            var canvas = document.createElement('canvas');
            canvas.height = 70;
            block.appendChild(canvas);
            container.appendChild(block);

            var labels = indices.map(function(index) {
                return data.options[index].label;
            });
            var values = indices.map(function(index) {
                return counts[index];
            });

            charts.push(new Chartjs(canvas, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: '#4c6ef5'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: {legend: {display: false}},
                    scales: {x: {beginAtZero: true, ticks: {precision: 0}}}
                }
            }));
        });
    };

    var renderStudentCheckboxes = function(data) {
        var container = root.querySelector('[data-region="student-checkboxes"]');
        container.innerHTML = '';

        data.students.forEach(function(student) {
            var domId = 'local-latracker-option-student-' + student.id;
            var row = document.createElement('div');
            row.className = 'form-check px-2 py-1';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input mr-2';
            checkbox.id = domId;
            checkbox.checked = true;
            checkbox.addEventListener('change', function() {
                if (checkbox.checked) {
                    selectedStudents.add(student.id);
                } else {
                    selectedStudents.delete(student.id);
                }
                renderCharts(currentData, selectedStudents);
            });

            var label = document.createElement('label');
            label.className = 'form-check-label mb-0';
            label.setAttribute('for', domId);
            label.textContent = student.name;

            row.appendChild(checkbox);
            row.appendChild(label);
            container.appendChild(row);
        });
    };

    var setAllStudents = function(data, visible) {
        selectedStudents = visible ? new Set(data.students.map(function(s) {
            return s.id;
        })) : new Set();

        root.querySelectorAll('[data-region="student-checkboxes"] input[type="checkbox"]').forEach(function(checkbox) {
            checkbox.checked = visible;
        });

        renderCharts(currentData, selectedStudents);
    };

    var populateSequenceSelect = function(data) {
        var select = root.querySelector('[data-region="sequence-select"]');
        var picker = root.querySelector('[data-region="sequence-picker"]');

        select.innerHTML = '';
        var placeholder = document.createElement('option');
        placeholder.value = '';
        Str.get_string('choose', 'moodle').then(function(text) {
            placeholder.textContent = text;
            return null;
        }).catch(function() {
            placeholder.textContent = '...';
        });
        select.appendChild(placeholder);

        data.students.forEach(function(student) {
            var option = document.createElement('option');
            option.value = student.id;
            option.textContent = student.name;
            select.appendChild(option);
        });

        picker.classList.remove('d-none');
    };

    /**
     * Renders one student's clicks for the current activity in
     * chronological order, grouped by question (in first-seen order),
     * with the final click in each group marked as the answer that was
     * actually left selected.
     */
    var renderSequence = function(studentId) {
        var container = root.querySelector('[data-region="sequence-list"]');
        container.innerHTML = '';

        if (!studentId || !currentData) {
            return;
        }

        var events = currentData.events
            .filter(function(event) {
                return event.studentid === studentId;
            })
            .slice()
            .sort(function(a, b) {
                return a.timestampms - b.timestampms;
            });

        if (!events.length) {
            Str.get_string('nosequencedata', 'local_latracker').then(function(text) {
                var empty = document.createElement('p');
                empty.className = 'text-muted small mb-0';
                empty.textContent = text;
                container.appendChild(empty);
                return null;
            }).catch(function() {
                // Best-effort status text.
            });
            return;
        }

        var groups = new Map();
        events.forEach(function(event) {
            var option = currentData.options[event.optionindex];
            var key = option.question || '';
            if (!groups.has(key)) {
                groups.set(key, []);
            }
            groups.get(key).push({label: option.label, timestampms: event.timestampms});
        });

        Str.get_string('finalclick', 'local_latracker').then(function(finaltext) {
            groups.forEach(function(clicks, question) {
                var block = document.createElement('div');
                block.className = 'mb-3';

                if (question) {
                    var heading = document.createElement('div');
                    heading.className = 'font-weight-bold small mb-1';
                    heading.textContent = question;
                    block.appendChild(heading);
                }

                var list = document.createElement('ol');
                list.className = 'local-latracker-sequence-list mb-0 small';
                clicks.forEach(function(click, index) {
                    var item = document.createElement('li');
                    var time = new Date(click.timestampms).toLocaleTimeString();
                    var isfinal = index === clicks.length - 1;
                    item.className = isfinal ? 'local-latracker-sequence-final' : '';

                    var labelSpan = document.createElement('span');
                    labelSpan.className = 'local-latracker-sequence-label';
                    labelSpan.textContent = click.label + (isfinal ? ' (' + finaltext + ')' : '');

                    var timeSpan = document.createElement('span');
                    timeSpan.className = 'local-latracker-sequence-time';
                    timeSpan.textContent = time;

                    item.appendChild(labelSpan);
                    item.appendChild(timeSpan);
                    list.appendChild(item);
                });
                block.appendChild(list);
                container.appendChild(block);
            });
            return null;
        }).catch(function() {
            // Best-effort; the sequence just won't be marked with a "final click" label.
        });
    };

    var loadActivity = function(cmid) {
        var chartsContainer = root.querySelector('[data-region="charts-container"]');
        var filterBtn = root.querySelector('[data-action="toggle-student-filter"]');
        var sequencePicker = root.querySelector('[data-region="sequence-picker"]');
        var sequenceList = root.querySelector('[data-region="sequence-list"]');

        chartsContainer.innerHTML = '';
        charts.forEach(function(existing) {
            existing.destroy();
        });
        charts = [];
        filterBtn.disabled = true;
        sequencePicker.classList.add('d-none');
        sequenceList.innerHTML = '';
        currentData = null;

        if (!cmid) {
            showStatus('');
            return;
        }

        Str.get_string('loadingoptions', 'local_latracker').then(function(text) {
            showStatus(text);
            return null;
        }).catch(function() {
            // Status text is best-effort.
        });

        Ajax.call([{
            methodname: 'local_latracker_get_option_clicks',
            args: {courseid: courseId, cmid: cmid}
        }])[0].then(function(data) {
            currentData = data;

            if (!data.events.length) {
                return Str.get_string('nooptionclickdata', 'local_latracker').then(function(text) {
                    showStatus(text);
                    return null;
                });
            }

            showStatus('');
            selectedStudents = new Set(data.students.map(function(s) {
                return s.id;
            }));
            renderStudentCheckboxes(data);
            renderCharts(data, selectedStudents);
            populateSequenceSelect(data);
            filterBtn.disabled = false;
            return null;
        }).catch(function(error) {
            showStatus(error.message);
        });
    };

    var bindFilterPanel = function() {
        var toggleBtn = root.querySelector('[data-action="toggle-student-filter"]');
        var panel = root.querySelector('[data-region="student-filter"]');
        if (toggleBtn && panel) {
            toggleBtn.addEventListener('click', function(event) {
                event.stopPropagation();
                if (!toggleBtn.disabled) {
                    panel.classList.toggle('d-none');
                }
            });
            document.addEventListener('click', function(event) {
                if (!panel.contains(event.target) && event.target !== toggleBtn) {
                    panel.classList.add('d-none');
                }
            });
        }

        var selectAllBtn = root.querySelector('[data-action="select-all"]');
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                if (currentData) {
                    setAllStudents(currentData, true);
                }
            });
        }

        var clearBtn = root.querySelector('[data-action="clear-selection"]');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (currentData) {
                    setAllStudents(currentData, false);
                }
            });
        }
    };

    return {
        /**
         * @param {String} rootId DOM id of the card wrapping the dropdowns/chart.
         * @param {Number} courseid
         */
        init: function(rootId, courseid) {
            root = document.getElementById(rootId);
            if (!root) {
                return;
            }
            courseId = courseid;

            var activitySelect = root.querySelector('[data-region="activity-select"]');
            if (activitySelect) {
                activitySelect.addEventListener('change', function() {
                    loadActivity(activitySelect.value ? parseInt(activitySelect.value, 10) : 0);
                });
            }

            var sequenceSelect = root.querySelector('[data-region="sequence-select"]');
            if (sequenceSelect) {
                sequenceSelect.addEventListener('change', function() {
                    renderSequence(sequenceSelect.value ? parseInt(sequenceSelect.value, 10) : 0);
                });
            }

            bindFilterPanel();
        }
    };
});
