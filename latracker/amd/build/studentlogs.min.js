// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * "Student navigation trail" section on analytics.php: the teacher picks
 * a student from a dropdown and sees that student's own entries from
 * Moodle's native log store merged with this plugin's own recorded quiz/
 * choice clicks (local_latracker_get_student_logs), as an organised
 * table - plus buttons to download either that one student's rows, or
 * every enrolled student's rows in one combined CSV
 * (local_latracker_get_all_student_logs).
 *
 * @module      local_latracker/studentlogs
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str'], function(Ajax, Str) {

    var courseId = 0;
    var root = null;
    var columnHeaders = null;
    var studentColumnHeaders = null;
    var currentRows = [];

    var showStatus = function(message) {
        root.querySelector('[data-region="status-message"]').textContent = message || '';
    };

    var loadColumnHeaders = function() {
        if (columnHeaders) {
            return Promise.resolve(columnHeaders);
        }
        return Str.get_strings([
            {key: 'logcolumn_time', component: 'local_latracker'},
            {key: 'logcolumn_context', component: 'local_latracker'},
            {key: 'logcolumn_component', component: 'local_latracker'},
            {key: 'logcolumn_event', component: 'local_latracker'}
        ]).then(function(strings) {
            columnHeaders = strings;
            return columnHeaders;
        });
    };

    var loadStudentColumnHeaders = function() {
        if (studentColumnHeaders) {
            return Promise.resolve(studentColumnHeaders);
        }
        return Promise.all([
            Str.get_string('logcolumn_student', 'local_latracker'),
            loadColumnHeaders()
        ]).then(function(results) {
            studentColumnHeaders = [results[0]].concat(results[1]);
            return studentColumnHeaders;
        });
    };

    var renderTable = function(rows) {
        var wrapper = root.querySelector('[data-region="log-table-wrapper"]');
        wrapper.innerHTML = '';

        if (!rows.length) {
            return;
        }

        var table = document.createElement('table');
        table.className = 'table table-sm table-striped';

        var thead = document.createElement('thead');
        var headerRow = document.createElement('tr');
        columnHeaders.forEach(function(text) {
            var th = document.createElement('th');
            th.textContent = text;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        rows.forEach(function(row) {
            var tr = document.createElement('tr');

            var timeTd = document.createElement('td');
            timeTd.className = 'local-latracker-log-time';
            timeTd.textContent = row.time;
            tr.appendChild(timeTd);

            var contextTd = document.createElement('td');
            contextTd.textContent = row.context;
            tr.appendChild(contextTd);

            var componentTd = document.createElement('td');
            var badge = document.createElement('span');
            badge.className = 'badge badge-light local-latracker-log-badge';
            badge.textContent = row.component;
            componentTd.appendChild(badge);
            tr.appendChild(componentTd);

            var eventTd = document.createElement('td');
            eventTd.textContent = row.eventname;
            tr.appendChild(eventTd);

            tbody.appendChild(tr);
        });
        table.appendChild(tbody);

        wrapper.appendChild(table);
    };

    var csvEscape = function(value) {
        var text = value === null || value === undefined ? '' : String(value);
        if (/["\n,]/.test(text)) {
            text = '"' + text.replace(/"/g, '""') + '"';
        }
        return text;
    };

    var triggerDownload = function(content, filename) {
        // A leading BOM makes accented characters display correctly when
        // the file is opened directly in Excel.
        var blob = new Blob(['﻿' + content], {type: 'text/csv;charset=utf-8;'});
        var url = URL.createObjectURL(blob);

        var link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    var downloadCsv = function(rows, studentId) {
        var lines = [columnHeaders.map(csvEscape).join(',')];
        rows.forEach(function(row) {
            lines.push([row.time, row.context, row.component, row.eventname].map(csvEscape).join(','));
        });
        triggerDownload(lines.join('\r\n'), 'trilha-navegacao-curso' + courseId + '-aluno' + studentId + '.csv');
    };

    var downloadAllCsv = function(rows) {
        var lines = [studentColumnHeaders.map(csvEscape).join(',')];
        rows.forEach(function(row) {
            lines.push([row.student, row.time, row.context, row.component, row.eventname].map(csvEscape).join(','));
        });
        triggerDownload(lines.join('\r\n'), 'trilha-navegacao-curso' + courseId + '-todos-os-alunos.csv');
    };

    var loadStudent = function(userid) {
        var downloadBtn = root.querySelector('[data-action="download-log-csv"]');
        var truncatedNotice = root.querySelector('[data-region="log-truncated-notice"]');

        currentRows = [];
        downloadBtn.disabled = true;
        truncatedNotice.classList.add('d-none');
        root.querySelector('[data-region="log-table-wrapper"]').innerHTML = '';

        if (!userid) {
            showStatus('');
            return;
        }

        Str.get_string('loadinglogs', 'local_latracker').then(function(text) {
            showStatus(text);
            return null;
        }).catch(function() {
            // Status text is best-effort.
        });

        loadColumnHeaders().then(function() {
            return Ajax.call([{
                methodname: 'local_latracker_get_student_logs',
                args: {courseid: courseId, userid: userid}
            }])[0];
        }).then(function(response) {
            currentRows = response.rows;

            if (!currentRows.length) {
                return Str.get_string('nologdata', 'local_latracker').then(function(text) {
                    showStatus(text);
                    return null;
                });
            }

            showStatus('');
            renderTable(currentRows);
            downloadBtn.disabled = false;

            if (response.truncated) {
                return Str.get_string('logtruncated', 'local_latracker', currentRows.length).then(function(text) {
                    truncatedNotice.textContent = text;
                    truncatedNotice.classList.remove('d-none');
                    return null;
                });
            }
            return null;
        }).catch(function(error) {
            showStatus(error.message);
        });
    };

    var downloadAll = function(button) {
        button.disabled = true;

        Str.get_string('loadingalllogs', 'local_latracker').then(function(text) {
            showStatus(text);
            return null;
        }).catch(function() {
            // Status text is best-effort.
        });

        loadStudentColumnHeaders().then(function() {
            return Ajax.call([{
                methodname: 'local_latracker_get_all_student_logs',
                args: {courseid: courseId}
            }])[0];
        }).then(function(response) {
            if (!response.rows.length) {
                return Str.get_string('noalllogdata', 'local_latracker').then(function(text) {
                    showStatus(text);
                    return null;
                });
            }

            showStatus('');
            downloadAllCsv(response.rows);
            return null;
        }).catch(function(error) {
            showStatus(error.message);
        }).then(function() {
            button.disabled = false;
        });
    };

    return {
        /**
         * @param {String} rootId DOM id of the card wrapping the dropdown/table.
         * @param {Number} courseid
         */
        init: function(rootId, courseid) {
            root = document.getElementById(rootId);
            if (!root) {
                return;
            }
            courseId = courseid;

            var select = root.querySelector('[data-region="log-student-select"]');
            if (select) {
                select.addEventListener('change', function() {
                    loadStudent(select.value ? parseInt(select.value, 10) : 0);
                });
            }

            var downloadBtn = root.querySelector('[data-action="download-log-csv"]');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    if (!currentRows.length || !select || !select.value) {
                        return;
                    }
                    downloadCsv(currentRows, select.value);
                });
            }

            var downloadAllBtn = root.querySelector('[data-action="download-all-log-csv"]');
            if (downloadAllBtn) {
                downloadAllBtn.addEventListener('click', function() {
                    downloadAll(downloadAllBtn);
                });
            }
        }
    };
});
