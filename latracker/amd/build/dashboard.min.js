// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Teacher dashboard interactions: opens the Google Picker, downloads the
 * selected CSV files' content client-side, then posts that content to
 * local_latracker_import_drive_files for parsing/storage. Gives constant
 * status feedback throughout (Nielsen's "visibility of system status").
 *
 * @module      local_latracker/dashboard
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str', 'local_latracker/googlepicker'], function(Ajax, Str, GooglePicker) {

    var DRIVE_READONLY_SCOPE = 'https://www.googleapis.com/auth/drive.readonly';

    var SELECTORS = {
        root: '.local-latracker-dashboard',
        status: '[data-region="status-message"]',
        importedList: '[data-region="imported-list"]',
        openPickerButton: '[data-action="open-picker"]'
    };

    var courseId = 0;
    var rootNode = null;

    var showStatus = function(message, type) {
        var status = rootNode.querySelector(SELECTORS.status);
        status.className = 'alert alert-' + (type || 'info');
        status.textContent = message;
        status.classList.remove('d-none');
    };

    var appendImportedFiles = function(filenames) {
        var list = rootNode.querySelector(SELECTORS.importedList);
        filenames.forEach(function(filename) {
            var item = document.createElement('div');
            item.className = 'text-success small';
            item.innerHTML = '<i class="fa fa-check mr-1" aria-hidden="true"></i>';
            item.append(filename);
            list.appendChild(item);
        });
    };

    var importFiles = function(files) {
        return Ajax.call([{
            methodname: 'local_latracker_import_drive_files',
            args: {
                courseid: courseId,
                files: files.map(function(file) {
                    return {driveid: file.id, filename: file.name, content: file.content};
                })
            }
        }])[0];
    };

    var handlePickerClick = function(button) {
        var config = {
            clientid: rootNode.dataset.googleclientid,
            apikey: rootNode.dataset.pickerapikey,
            scope: DRIVE_READONLY_SCOPE
        };

        button.disabled = true;

        Str.get_string('opening', 'local_latracker').then(function(text) {
            showStatus(text, 'info');
            return null;
        }).catch(function() {
            // Status text is best-effort; the flow continues either way.
        });

        GooglePicker.pickAndDownload(config)
            .then(function(files) {
                if (!files.length) {
                    return Str.get_string('nofileselected', 'local_latracker').then(function(text) {
                        showStatus(text, 'info');
                        return null;
                    });
                }

                return Str.get_string('importing', 'local_latracker').then(function(text) {
                    showStatus(text, 'info');
                    return importFiles(files);
                }).then(function(response) {
                    appendImportedFiles(response.imported.map(function(item) {
                        return item.filename;
                    }));
                    return Str.get_string('importsuccess', 'local_latracker', response.imported.length);
                }).then(function(text) {
                    showStatus(text, 'success');
                    return null;
                });
            })
            .catch(function(error) {
                showStatus(error.message, 'danger');
            })
            .then(function() {
                button.disabled = false;
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

            var openPickerButton = rootNode.querySelector(SELECTORS.openPickerButton);
            if (openPickerButton) {
                openPickerButton.addEventListener('click', function() {
                    handlePickerClick(openPickerButton);
                });
            }
        }
    };
});
