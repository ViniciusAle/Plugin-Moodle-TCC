// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Teacher dashboard interactions: lists the teacher's Google Drive CSV
 * files, lets them pick which ones to import, and gives constant status
 * feedback (Nielsen's "visibility of system status" heuristic).
 *
 * @module      local_latracker/dashboard
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str', 'core/notification'], function(Ajax, Str, Notification) {

    var SELECTORS = {
        root: '.local-latracker-dashboard',
        status: '[data-region="status-message"]',
        fileList: '[data-region="file-list"]',
        refreshButton: '[data-action="refresh-files"]',
        importButton: '[data-action="import-selected"]'
    };

    var courseId = 0;
    var rootNode = null;

    var showStatus = function(message, type) {
        var status = rootNode.querySelector(SELECTORS.status);
        status.className = 'alert alert-' + (type || 'info');
        status.textContent = message;
        status.classList.remove('d-none');
    };

    var setImportButtonState = function() {
        var checked = rootNode.querySelectorAll('[data-region="file-list"] input[type="checkbox"]:checked');
        rootNode.querySelector(SELECTORS.importButton).disabled = (checked.length === 0);
    };

    var renderFiles = function(files) {
        var listNode = rootNode.querySelector(SELECTORS.fileList);
        listNode.innerHTML = '';

        if (!files.length) {
            return Str.get_string('nocsvfiles', 'local_latracker').then(function(text) {
                var empty = document.createElement('div');
                empty.className = 'text-muted small p-2';
                empty.textContent = text;
                listNode.appendChild(empty);
                return null;
            }).catch(Notification.exception);
        }

        files.forEach(function(file) {
            var item = document.createElement('label');
            item.className = 'list-group-item d-flex align-items-center';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'mr-2';
            checkbox.value = file.id;
            checkbox.addEventListener('change', setImportButtonState);

            var text = document.createElement('span');
            text.textContent = file.name;

            item.appendChild(checkbox);
            item.appendChild(text);
            listNode.appendChild(item);
        });

        return Promise.resolve();
    };

    var loadFiles = function() {
        return Ajax.call([{
            methodname: 'local_latracker_get_drive_files',
            args: {courseid: courseId}
        }])[0].then(function(response) {
            if (!response.connected) {
                rootNode.querySelector('[data-region="connect-panel"]').classList.remove('d-none');
                rootNode.querySelector('[data-region="files-panel"]').classList.add('d-none');
                return null;
            }
            return renderFiles(response.files).then(function() {
                setImportButtonState();
                return null;
            });
        }).catch(function(error) {
            showStatus(error.message, 'danger');
        });
    };

    var importSelected = function() {
        var checked = rootNode.querySelectorAll('[data-region="file-list"] input[type="checkbox"]:checked');
        var fileids = Array.prototype.map.call(checked, function(cb) {
            return cb.value;
        });

        if (!fileids.length) {
            return;
        }

        Str.get_string('importing', 'local_latracker').then(function(text) {
            showStatus(text, 'info');
            return null;
        }).catch(Notification.exception);

        Ajax.call([{
            methodname: 'local_latracker_import_drive_files',
            args: {courseid: courseId, fileids: fileids}
        }])[0].then(function(response) {
            return Str.get_string('importsuccess', 'local_latracker', response.imported.length).then(function(text) {
                showStatus(text, 'success');
                return loadFiles();
            });
        }).catch(function(error) {
            showStatus(error.message, 'danger');
        });
    };

    return {
        /**
         * @param {Number} courseid
         */
        init: function(courseid) {
            courseId = courseid;
            rootNode = document.querySelector(SELECTORS.root);
            if (!rootNode) {
                return;
            }

            var refreshBtn = rootNode.querySelector(SELECTORS.refreshButton);
            if (refreshBtn) {
                refreshBtn.addEventListener('click', loadFiles);
            }

            var importBtn = rootNode.querySelector(SELECTORS.importButton);
            if (importBtn) {
                importBtn.addEventListener('click', importSelected);
            }

            var filesPanel = rootNode.querySelector('[data-region="files-panel"]');
            if (filesPanel && !filesPanel.classList.contains('d-none')) {
                loadFiles();
            }
        }
    };
});
