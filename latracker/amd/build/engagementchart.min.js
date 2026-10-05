// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Interactive "engagement over time" chart on analytics.php: one coloured
 * line per student (cumulative minutes on the platform, day by day), with
 * a dropdown of checkboxes to choose which students' lines are shown.
 *
 * This talks to Chart.js directly (via core/chartjs, the same library
 * Moodle's own core/chart_output_chartjs uses) instead of going through
 * core's server-rendered Chart API, because toggling dataset visibility
 * from a custom control needs a live handle on the Chart.js instance,
 * which the server-rendered output does not expose.
 *
 * @module      local_latracker/engagementchart
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/chartjs'], function(Chartjs) {

    // A palette with enough visually distinct colours for a typical class;
    // it cycles if there are more students than colours.
    var COLORS = [
        '#4c6ef5', '#f76707', '#37b24d', '#f03e3e', '#ae3ec9', '#1098ad',
        '#f59f00', '#5c940d', '#1864ab', '#e64980', '#495057', '#0ca678'
    ];

    var colorFor = function(index) {
        return COLORS[index % COLORS.length];
    };

    var buildDatasets = function(students) {
        return students.map(function(student, index) {
            var color = colorFor(index);
            return {
                label: student.name,
                data: student.data,
                borderColor: color,
                backgroundColor: color,
                pointBackgroundColor: color,
                pointRadius: 2,
                tension: 0.2,
                fill: false
            };
        });
    };

    var renderCheckboxes = function(container, students, chart) {
        container.innerHTML = '';

        students.forEach(function(student, index) {
            var domId = 'local-latracker-student-' + student.id;

            var row = document.createElement('div');
            row.className = 'form-check d-flex align-items-center px-2 py-1';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input mr-2';
            checkbox.id = domId;
            checkbox.checked = true;
            checkbox.addEventListener('change', function() {
                chart.setDatasetVisibility(index, checkbox.checked);
                chart.update();
            });

            var swatch = document.createElement('span');
            swatch.className = 'd-inline-block rounded-circle mr-2';
            swatch.style.width = '10px';
            swatch.style.height = '10px';
            swatch.style.backgroundColor = colorFor(index);

            var label = document.createElement('label');
            label.className = 'form-check-label mb-0';
            label.setAttribute('for', domId);
            label.textContent = student.name;

            row.appendChild(checkbox);
            row.appendChild(swatch);
            row.appendChild(label);
            container.appendChild(row);
        });
    };

    var setAllVisibility = function(container, chart, visible) {
        container.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox, index) {
            checkbox.checked = visible;
            chart.setDatasetVisibility(index, visible);
        });
        chart.update();
    };

    var bindFilterPanel = function(root, chart, checkboxContainer) {
        var toggleBtn = root.querySelector('[data-action="toggle-student-filter"]');
        var panel = root.querySelector('[data-region="student-filter"]');
        if (toggleBtn && panel) {
            toggleBtn.addEventListener('click', function(event) {
                event.stopPropagation();
                panel.classList.toggle('d-none');
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
                setAllVisibility(checkboxContainer, chart, true);
            });
        }

        var clearBtn = root.querySelector('[data-action="clear-selection"]');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                setAllVisibility(checkboxContainer, chart, false);
            });
        }
    };

    return {
        /**
         * @param {String} rootId DOM id of the card wrapping the chart/dropdown.
         */
        init: function(rootId) {
            var root = document.getElementById(rootId);
            if (!root) {
                return;
            }

            var dataNode = root.querySelector('[data-region="engagement-data"]');
            var canvas = root.querySelector('[data-region="engagement-canvas"]');
            var checkboxContainer = root.querySelector('[data-region="student-checkboxes"]');
            if (!dataNode || !canvas || !checkboxContainer) {
                return;
            }

            var data = JSON.parse(dataNode.textContent);
            if (!data.students || !data.students.length) {
                return;
            }

            var chart = new Chartjs(canvas, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: buildDatasets(data.students)
                },
                options: {
                    interaction: {mode: 'nearest', intersect: false},
                    plugins: {legend: {display: false}},
                    scales: {y: {beginAtZero: true}}
                }
            });

            renderCheckboxes(checkboxContainer, data.students, chart);
            bindFilterPanel(root, chart, checkboxContainer);
        }
    };
});
