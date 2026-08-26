<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_latracker\output;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderable/templatable data for the teacher dashboard (index.php).
 *
 * The Google Drive file picker is entirely client-side (Google Identity
 * Services + the Picker widget, see amd/src/googlepicker.js), so this
 * class only needs to pass through the public configuration (client id,
 * Picker API key) required to bootstrap it in the browser - never a
 * secret, and never anything the Moodle server itself needs to call out
 * to Google for.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard_page implements renderable, templatable {

    protected int $courseid;
    protected bool $canmanageintegrations;
    protected moodle_url $analyticsurl;

    public function __construct(int $courseid, bool $canmanageintegrations, moodle_url $analyticsurl) {
        $this->courseid = $courseid;
        $this->canmanageintegrations = $canmanageintegrations;
        $this->analyticsurl = $analyticsurl;
    }

    public function export_for_template(renderer_base $output): array {
        $googleclientid = get_config('local_latracker', 'googleclientid');
        $pickerapikey = get_config('local_latracker', 'pickerapikey');

        return [
            'courseid'              => $this->courseid,
            'canmanageintegrations' => $this->canmanageintegrations,
            'analyticsurl'          => $this->analyticsurl->out(false),
            'googleclientid'        => $googleclientid,
            'pickerapikey'          => $pickerapikey,
            'hasgoogleconfig'       => !empty($googleclientid) && !empty($pickerapikey),
        ];
    }
}
