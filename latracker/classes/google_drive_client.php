<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_latracker;

use core\oauth2\api;
use core\oauth2\issuer;
use core\oauth2\client;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Thin wrapper around Moodle's core OAuth2 API to talk to Google Drive.
 *
 * Design decision: rather than inventing a custom table to store Google
 * access/refresh tokens (which would mean re-implementing token storage,
 * encryption and refresh logic), this plugin reuses core's OAuth2 system
 * (admin/tool/oauth2, table oauth2_issuer / oauth2_access_token). The site
 * administrator only has to register a "Google" OAuth2 service once under
 * Site administration > Server > OAuth 2 services (see README.md), and
 * pick that issuer on this plugin's settings page. This is both more
 * secure (tokens are handled by core, following upstream security fixes)
 * and more consistent with "official Moodle coding style".
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class google_drive_client {

    /** @var string Scope requested in addition to the issuer defaults. */
    const DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive.readonly';

    /** @var client */
    protected $client;

    /**
     * @param moodle_url $returnurl Page to return to after the Google consent screen.
     * @throws \moodle_exception If no OAuth2 issuer is configured for this plugin.
     */
    public function __construct(moodle_url $returnurl) {
        $issuer = self::get_configured_issuer();
        if (!$issuer) {
            throw new \moodle_exception('nooauthissuer', 'local_latracker');
        }

        $this->client = api::get_user_oauth_client($issuer, $returnurl, self::DRIVE_SCOPE, false);
    }

    /**
     * Returns the oauth2_issuer configured in the plugin settings, if any.
     */
    public static function get_configured_issuer(): ?issuer {
        $issuerid = (int) get_config('local_latracker', 'oauthissuerid');
        if (!$issuerid) {
            return null;
        }
        return api::get_issuer($issuerid) ?: null;
    }

    /**
     * Whether the current user already completed the Google OAuth handshake.
     */
    public function is_connected(): bool {
        return $this->client->is_logged_in();
    }

    /**
     * URL that starts (or resumes) the Google consent flow.
     */
    public function get_login_url(): moodle_url {
        return new moodle_url($this->client->get_login_url());
    }

    /**
     * Revokes the current user's Google session for this plugin.
     */
    public function disconnect(): void {
        $this->client->log_out();
    }

    /**
     * Lists the CSV files visible to the connected Google account.
     *
     * @return array List of stdClass{id, name, modifiedTime, size}
     */
    public function list_csv_files(): array {
        $query = http_build_query([
            'q'        => "mimeType='text/csv' and trashed=false",
            'fields'   => 'files(id, name, modifiedTime, size)',
            'pageSize' => 100,
            'spaces'   => 'drive',
        ]);

        $response = $this->client->get('https://www.googleapis.com/drive/v3/files?' . $query);
        $data = json_decode($response);

        if (!$data || !isset($data->files)) {
            return [];
        }

        return $data->files;
    }

    /**
     * Downloads the raw content (CSV text) of a Drive file.
     */
    public function download_file(string $drivefileid): string {
        $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($drivefileid) . '?alt=media';
        return $this->client->get($url);
    }
}
