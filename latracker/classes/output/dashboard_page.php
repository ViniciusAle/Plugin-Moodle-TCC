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
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard_page implements renderable, templatable {

    protected int $courseid;
    protected bool $connected;
    protected bool $canmanageintegrations;
    protected moodle_url $connecturl;
    protected moodle_url $analyticsurl;

    public function __construct(
        int $courseid,
        bool $connected,
        bool $canmanageintegrations,
        moodle_url $connecturl,
        moodle_url $analyticsurl
    ) {
        $this->courseid = $courseid;
        $this->connected = $connected;
        $this->canmanageintegrations = $canmanageintegrations;
        $this->connecturl = $connecturl;
        $this->analyticsurl = $analyticsurl;
    }

    public function export_for_template(renderer_base $output): array {
        return [
            'courseid'              => $this->courseid,
            'connected'             => $this->connected,
            'canmanageintegrations' => $this->canmanageintegrations,
            'connecturl'            => $this->connecturl->out(false),
            'analyticsurl'          => $this->analyticsurl->out(false),
        ];
    }
}
