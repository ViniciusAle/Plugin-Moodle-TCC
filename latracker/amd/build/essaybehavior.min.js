// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * "Writing behaviour in essay questions" indicator on analytics.php.
 *
 * The teacher picks a quiz, then one of its essay questions, and sees for
 * every student: a stacked bar of typed vs pasted characters in the final
 * answer, a table with focus time, typing time, pauses, words and pastes,
 * and - for one chosen student - how the answer grew over time, with every
 * paste marked on the line.
 *
 * The activity dropdown triggers one AJAX call (local_latracker_get_essay_behavior)
 * returning every student's summary; switching question just re-reads that
 * data client-side. A student's (larger) evolution timeline is fetched only
 * when that student is chosen, and cached.
 *
 * @module      local_latracker/essaybehavior
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str', 'core/chartjs'], function(Ajax, Str, Chartjs) {

    var COLOR_TYPED = '#4c6ef5';
    var COLOR_PASTED = '#f03e3e';
    var COLOR_WORDS = '#37b24d';

    /** Pasted share (%) at or above which a row is flagged. Indicative, not a verdict. */
    var PASTED_WARN = 20;
    var PASTED_HIGH = 50;

    var STRING_KEYS = [
        ['essayquestion', 'local_latracker'],
        ['essaycol_student', 'local_latracker'],
        ['essaycol_focus', 'local_latracker'],
        ['essaycol_typing', 'local_latracker'],
        ['essaycol_pauses', 'local_latracker'],
        ['essaycol_words', 'local_latracker'],
        ['essaycol_pastes', 'local_latracker'],
        ['essaycol_pastedchars', 'local_latracker'],
        ['essaycol_pastedpercent', 'local_latracker'],
        ['essaytypedchars', 'local_latracker'],
        ['essaypastedchars', 'local_latracker'],
        ['essaycharsseries', 'local_latracker'],
        ['essaywordsseries', 'local_latracker'],
        ['essaypasteseries', 'local_latracker'],
        ['loadingessay', 'local_latracker'],
        ['noessaydata', 'local_latracker'],
        ['noessayevolution', 'local_latracker'],
        ['choose', 'moodle']
    ];

    var courseId = 0;
    var root = null;
    var strings = null;
    var data = null;
    var requestId = 0;
    var compositionChart = null;
    var evolutionChart = null;
    var evolutionCache = {};

    var loadStrings = function() {
        if (strings) {
            return Promise.resolve(strings);
        }
        return Str.get_strings(STRING_KEYS.map(function(entry) {
            return {key: entry[0], component: entry[1]};
        })).then(function(list) {
            strings = {};
            STRING_KEYS.forEach(function(entry, index) {
                strings[entry[0]] = list[index];
            });
            return strings;
        });
    };

    var region = function(name) {
        return root.querySelector('[data-region="' + name + '"]');
    };

    var showStatus = function(message) {
        region('status-message').textContent = message || '';
    };

    var formatDuration = function(total) {
        var seconds = Math.max(0, Math.round(total));
        if (seconds < 60) {
            return seconds + ' s';
        }
        var minutes = Math.floor(seconds / 60);
        var rest = seconds % 60;
        return minutes + ' min' + (rest ? ' ' + rest + ' s' : '');
    };

    var destroyCharts = function() {
        if (compositionChart) {
            compositionChart.destroy();
            compositionChart = null;
        }
        if (evolutionChart) {
            evolutionChart.destroy();
            evolutionChart = null;
        }
    };

    var resetResults = function() {
        destroyCharts();
        data = null;
        evolutionCache = {};
        region('question-picker').classList.add('d-none');
        region('essay-results').classList.add('d-none');
        region('essay-evolution-wrapper').classList.add('d-none');
        region('evolution-status').textContent = '';
        region('essay-table-wrapper').innerHTML = '';
        showStatus('');
    };

    var currentSlot = function() {
        return parseInt(region('question-select').value, 10);
    };

    var studentName = function(id) {
        var match = data.students.find(function(student) {
            return student.id === id;
        });
        return match ? match.name : String(id);
    };

    var rowsForSlot = function(slot) {
        return data.summaries
            .filter(function(summary) {
                return summary.slot === slot;
            })
            .sort(function(a, b) {
                return (b.pastedpercent - a.pastedpercent) || studentName(a.studentid).localeCompare(studentName(b.studentid));
            });
    };

    var pill = function(percent) {
        var span = document.createElement('span');
        var level = percent >= PASTED_HIGH ? 'high' : (percent >= PASTED_WARN ? 'warn' : 'ok');
        span.className = 'local-latracker-pill local-latracker-pill-' + level;
        span.textContent = percent + '%';
        return span;
    };

    var renderComposition = function(rows) {
        var wrapper = region('essay-composition-wrapper');
        var canvas = region('essay-composition-canvas');
        wrapper.style.height = (rows.length * 34 + 90) + 'px';

        if (compositionChart) {
            compositionChart.destroy();
        }

        var pasted = rows.map(function(row) {
            return Math.min(row.pastedchars, row.finalchars);
        });
        var typed = rows.map(function(row, index) {
            return Math.max(0, row.finalchars - pasted[index]);
        });

        compositionChart = new Chartjs(canvas, {
            type: 'bar',
            data: {
                labels: rows.map(function(row) {
                    return studentName(row.studentid);
                }),
                datasets: [
                    {label: strings.essaytypedchars, data: typed, backgroundColor: COLOR_TYPED},
                    {label: strings.essaypastedchars, data: pasted, backgroundColor: COLOR_PASTED}
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {legend: {position: 'bottom'}},
                scales: {
                    x: {stacked: true, beginAtZero: true, ticks: {precision: 0}},
                    y: {stacked: true}
                }
            }
        });
    };

    var renderTable = function(rows) {
        var wrapper = region('essay-table-wrapper');
        wrapper.innerHTML = '';

        var table = document.createElement('table');
        table.className = 'table table-sm table-striped';

        var headerRow = document.createElement('tr');
        [
            strings.essaycol_student, strings.essaycol_focus, strings.essaycol_typing, strings.essaycol_pauses,
            strings.essaycol_words, strings.essaycol_pastes, strings.essaycol_pastedchars, strings.essaycol_pastedpercent
        ].forEach(function(text) {
            var th = document.createElement('th');
            th.textContent = text;
            headerRow.appendChild(th);
        });
        var thead = document.createElement('thead');
        thead.appendChild(headerRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        rows.forEach(function(row) {
            var tr = document.createElement('tr');
            [
                studentName(row.studentid), formatDuration(row.focusseconds), formatDuration(row.typingseconds),
                row.pausecount, row.finalwords, row.pastecount, row.pastedchars
            ].forEach(function(value, index) {
                var td = document.createElement('td');
                if (index > 0) {
                    td.className = 'local-latracker-log-time';
                }
                td.textContent = value;
                tr.appendChild(td);
            });

            var percentTd = document.createElement('td');
            percentTd.appendChild(pill(row.pastedpercent));
            tr.appendChild(percentTd);
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        wrapper.appendChild(table);
    };

    var populateEvolutionSelect = function(rows) {
        var select = region('evolution-select');
        var previous = select.value;
        select.innerHTML = '';

        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = strings.choose;
        select.appendChild(placeholder);

        rows.forEach(function(row) {
            var option = document.createElement('option');
            option.value = row.studentid;
            option.textContent = studentName(row.studentid);
            select.appendChild(option);
        });

        if (previous && select.querySelector('option[value="' + previous + '"]')) {
            select.value = previous;
        }
    };

    /** Y value (characters) for a paste marker: the first snapshot at/after the paste, else the last. */
    var charsAtOrAfter = function(points, timestamp) {
        for (var i = 0; i < points.length; i++) {
            if (points[i][0] >= timestamp) {
                return points[i][1];
            }
        }
        return points.length ? points[points.length - 1][1] : 0;
    };

    var renderEvolution = function() {
        var wrapper = region('essay-evolution-wrapper');
        var status = region('evolution-status');
        var studentId = parseInt(region('evolution-select').value, 10);

        if (evolutionChart) {
            evolutionChart.destroy();
            evolutionChart = null;
        }
        wrapper.classList.add('d-none');
        status.textContent = '';

        if (!studentId || !evolutionCache[studentId]) {
            return;
        }

        var slot = currentSlot();
        var entry = evolutionCache[studentId].find(function(item) {
            return item.slot === slot;
        });
        var points = entry ? JSON.parse(entry.points) : [];
        var pastes = entry ? JSON.parse(entry.pastes) : [];

        if (!points.length) {
            status.textContent = strings.noessayevolution;
            return;
        }

        wrapper.classList.remove('d-none');

        evolutionChart = new Chartjs(region('essay-evolution-canvas'), {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: strings.essaycharsseries,
                        data: points.map(function(p) {
                            return {x: p[0], y: p[1]};
                        }),
                        borderColor: COLOR_TYPED,
                        backgroundColor: COLOR_TYPED,
                        yAxisID: 'y',
                        tension: 0.2,
                        pointRadius: 2
                    },
                    {
                        label: strings.essaywordsseries,
                        data: points.map(function(p) {
                            return {x: p[0], y: p[2]};
                        }),
                        borderColor: COLOR_WORDS,
                        backgroundColor: COLOR_WORDS,
                        yAxisID: 'y1',
                        tension: 0.2,
                        pointRadius: 2
                    },
                    {
                        type: 'scatter',
                        label: strings.essaypasteseries,
                        data: pastes.map(function(p) {
                            return {x: p[0], y: charsAtOrAfter(points, p[0]), pasted: p[1]};
                        }),
                        backgroundColor: COLOR_PASTED,
                        borderColor: COLOR_PASTED,
                        pointStyle: 'triangle',
                        pointRadius: 8,
                        yAxisID: 'y'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {mode: 'nearest', intersect: false},
                plugins: {
                    legend: {position: 'bottom'},
                    tooltip: {
                        callbacks: {
                            title: function(items) {
                                return new Date(items[0].parsed.x).toLocaleTimeString();
                            },
                            label: function(context) {
                                if (context.raw && context.raw.pasted !== undefined) {
                                    return strings.essaypasteseries + ': ' + context.raw.pasted;
                                }
                                return context.dataset.label + ': ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        ticks: {
                            maxTicksLimit: 8,
                            callback: function(value) {
                                return new Date(value).toLocaleTimeString();
                            }
                        }
                    },
                    y: {beginAtZero: true, position: 'left', title: {display: true, text: strings.essaycharsseries}},
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: {drawOnChartArea: false},
                        title: {display: true, text: strings.essaywordsseries}
                    }
                }
            }
        });
    };

    var loadEvolution = function(studentId, cmid) {
        if (evolutionCache[studentId]) {
            renderEvolution();
            return;
        }

        var token = requestId;
        Ajax.call([{
            methodname: 'local_latracker_get_essay_behavior',
            args: {courseid: courseId, cmid: cmid, userid: studentId}
        }])[0].then(function(response) {
            if (token !== requestId) {
                return null;
            }
            evolutionCache[studentId] = response.evolution;
            renderEvolution();
            return null;
        }).catch(function(error) {
            region('evolution-status').textContent = error.message;
        });
    };

    var render = function() {
        var rows = rowsForSlot(currentSlot());
        renderComposition(rows);
        renderTable(rows);
        populateEvolutionSelect(rows);
        renderEvolution();
    };

    var populateQuestions = function() {
        var select = region('question-select');
        select.innerHTML = '';

        data.questions.forEach(function(question) {
            var option = document.createElement('option');
            option.value = question.slot;
            var label = strings.essayquestion + ' ' + (question.number || question.slot);
            option.textContent = question.context ? label + ' - ' + question.context.slice(0, 70) : label;
            select.appendChild(option);
        });

        region('question-picker').classList.remove('d-none');
    };

    var loadActivity = function(cmid) {
        requestId++;
        var token = requestId;
        resetResults();

        if (!cmid) {
            return;
        }

        loadStrings().then(function() {
            showStatus(strings.loadingessay);
            return Ajax.call([{
                methodname: 'local_latracker_get_essay_behavior',
                args: {courseid: courseId, cmid: cmid, userid: 0}
            }])[0];
        }).then(function(response) {
            if (token !== requestId) {
                return null;
            }

            data = response;
            if (!response.summaries.length) {
                showStatus(strings.noessaydata);
                return null;
            }

            showStatus('');
            populateQuestions();
            // Charts need a visible container to measure, so show it first.
            region('essay-results').classList.remove('d-none');
            render();
            return null;
        }).catch(function(error) {
            showStatus(error.message);
        });
    };

    return {
        /**
         * @param {String} rootId DOM id of the card wrapping the indicator.
         * @param {Number} courseid
         */
        init: function(rootId, courseid) {
            root = document.getElementById(rootId);
            if (!root) {
                return;
            }
            courseId = courseid;

            var activitySelect = region('activity-select');
            if (!activitySelect) {
                return;
            }

            activitySelect.addEventListener('change', function() {
                loadActivity(activitySelect.value ? parseInt(activitySelect.value, 10) : 0);
            });

            region('question-select').addEventListener('change', function() {
                if (data) {
                    render();
                }
            });

            region('evolution-select').addEventListener('change', function() {
                var studentId = parseInt(region('evolution-select').value, 10);
                if (!studentId) {
                    renderEvolution();
                    return;
                }
                loadEvolution(studentId, parseInt(activitySelect.value, 10));
            });
        }
    };
});
