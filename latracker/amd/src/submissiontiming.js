// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Interactive "submission timing" chart on analytics.php: how long before
 * its expected completion date each activity was actually marked
 * complete, bucketed into 5 time-to-deadline ranges, with one coloured
 * bar series per activity and a dropdown to choose which activities are
 * shown.
 *
 * Structurally this mirrors amd/src/engagementchart.js (same embedded
 * JSON + checkbox-dropdown + Chart.js pattern), just with grouped bars
 * instead of lines, and activities instead of students as the series.
 *
 * @module      local_latracker/submissiontiming
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/chartjs'], function(Chartjs) {

    var COLORS = [
        '#4c6ef5', '#f76707', '#37b24d', '#f03e3e', '#ae3ec9', '#1098ad',
        '#f59f00', '#5c940d', '#1864ab', '#e64980', '#495057', '#0ca678'
    ];

    var colorFor = function(index) {
        return COLORS[index % COLORS.length];
    };

    var buildDatasets = function(activities) {
        return activities.map(function(activity, index) {
            var color = colorFor(index);
            return {
                label: activity.name,
                data: activity.data,
                backgroundColor: color
            };
        });
    };

    var renderCheckboxes = function(container, activities, chart) {
        container.innerHTML = '';

        activities.forEach(function(activity, index) {
            var domId = 'local-latracker-activity-' + activity.cmid;

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
            label.textContent = activity.name;

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
        var toggleBtn = root.querySelector('[data-action="toggle-activity-filter"]');
        var panel = root.querySelector('[data-region="activity-filter"]');
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

            var dataNode = root.querySelector('[data-region="submissiontiming-data"]');
            var canvas = root.querySelector('[data-region="submissiontiming-canvas"]');
            var checkboxContainer = root.querySelector('[data-region="activity-checkboxes"]');
            if (!dataNode || !canvas || !checkboxContainer) {
                return;
            }

            var data = JSON.parse(dataNode.textContent);
            if (!data.activities || !data.activities.length) {
                return;
            }

            var chart = new Chartjs(canvas, {
                type: 'bar',
                data: {
                    labels: data.buckets,
                    datasets: buildDatasets(data.activities)
                },
                options: {
                    plugins: {legend: {display: false}},
                    scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
                }
            });

            renderCheckboxes(checkboxContainer, data.activities, chart);
            bindFilterPanel(root, chart, checkboxContainer);
        }
    };
});
