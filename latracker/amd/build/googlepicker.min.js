// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Thin wrapper around Google Identity Services + the Google Picker widget.
 *
 * Everything here runs in the browser: it asks the teacher to sign in to
 * Google (a popup, not a full-page redirect through Moodle), lets them
 * browse/select CSV files with Google's own picker UI, and downloads the
 * selected files' content directly from googleapis.com using the token
 * the popup returned. The Moodle server is never asked to talk to Google
 * itself, so this does not depend on the Moodle server having outbound
 * network access to Google (which is what made the previous server-side
 * "list Drive files" call hang indefinitely on some hosts).
 *
 * @module      local_latracker/googlepicker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var librariesPromise = null;

    var loadScript = function(src) {
        return new Promise(function(resolve, reject) {
            if (document.querySelector('script[data-latracker-src="' + src + '"]')) {
                resolve();
                return;
            }
            var script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.dataset.latrackerSrc = src;
            script.addEventListener('load', function() {
                resolve();
            });
            script.addEventListener('error', function() {
                reject(new Error('Failed to load ' + src));
            });
            document.head.appendChild(script);
        });
    };

    /**
     * Loads the Google Identity Services client and the Picker library
     * (both from Google's own CDN) exactly once per page.
     */
    var ensureLibraries = function() {
        if (!librariesPromise) {
            librariesPromise = Promise.all([
                loadScript('https://accounts.google.com/gsi/client'),
                loadScript('https://apis.google.com/js/api.js').then(function() {
                    return new Promise(function(resolve) {
                        // eslint-disable-next-line no-undef
                        gapi.load('picker', {callback: resolve});
                    });
                })
            ]);
        }
        return librariesPromise;
    };

    /**
     * Opens Google's OAuth consent popup and resolves with a short-lived
     * access token scoped to read-only Drive access.
     */
    var requestAccessToken = function(clientid, scope) {
        return new Promise(function(resolve, reject) {
            // eslint-disable-next-line no-undef
            var tokenClient = google.accounts.oauth2.initTokenClient({
                client_id: clientid,
                scope: scope,
                callback: function(response) {
                    if (response.error) {
                        reject(new Error(response.error));
                        return;
                    }
                    resolve(response.access_token);
                },
                error_callback: function(error) {
                    reject(new Error((error && error.type) || 'popup_closed'));
                }
            });
            tokenClient.requestAccessToken({prompt: ''});
        });
    };

    /**
     * Shows the Picker filtered to CSV files and resolves with the array
     * of picked docs (empty array if the teacher cancels).
     */
    var showPicker = function(apikey, accessToken) {
        return new Promise(function(resolve) {
            // eslint-disable-next-line no-undef
            var view = new google.picker.DocsView(google.picker.ViewId.DOCS)
                .setMimeTypes('text/csv')
                .setIncludeFolders(true);

            // eslint-disable-next-line no-undef
            var picker = new google.picker.PickerBuilder()
                .addView(view)
                .setOAuthToken(accessToken)
                .setDeveloperKey(apikey)
                // eslint-disable-next-line no-undef
                .enableFeature(google.picker.Feature.MULTISELECT_ENABLED)
                .setCallback(function(data) {
                    // eslint-disable-next-line no-undef
                    if (data.action === google.picker.Action.PICKED) {
                        resolve(data.docs);
                        // eslint-disable-next-line no-undef
                    } else if (data.action === google.picker.Action.CANCEL) {
                        resolve([]);
                    }
                })
                .build();
            picker.setVisible(true);
        });
    };

    var downloadFile = function(fileid, accessToken) {
        return window.fetch(
            'https://www.googleapis.com/drive/v3/files/' + encodeURIComponent(fileid) + '?alt=media',
            {headers: {Authorization: 'Bearer ' + accessToken}}
        ).then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.text();
        });
    };

    return {
        /**
         * Full flow: auth popup -> picker -> download selected files.
         *
         * @param {Object} config
         * @param {String} config.clientid Google OAuth 2.0 Client ID.
         * @param {String} config.apikey Google Picker API key.
         * @param {String} config.scope OAuth scope to request.
         * @return {Promise<Array<{id: String, name: String, content: String}>>}
         *         Empty array if the teacher closes the picker without selecting anything.
         */
        pickAndDownload: function(config) {
            return ensureLibraries()
                .then(function() {
                    return requestAccessToken(config.clientid, config.scope);
                })
                .then(function(accessToken) {
                    return showPicker(config.apikey, accessToken).then(function(docs) {
                        var downloads = docs.map(function(doc) {
                            return downloadFile(doc.id, accessToken).then(function(content) {
                                return {id: doc.id, name: doc.name, content: content};
                            });
                        });
                        return Promise.all(downloads);
                    });
                });
        }
    };
});
