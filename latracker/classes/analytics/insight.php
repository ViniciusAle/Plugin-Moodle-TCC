<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_latracker\analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Contract for a pluggable Learning Analytics insight.
 *
 * To add a new insight to analytics.php, create a class implementing this
 * interface and register it in analytics_engine::get_providers(). Nothing
 * else needs to change - the page and template loop over the registry.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface insight {

    /**
     * Unique short identifier, used as a DOM id / template key.
     */
    public function get_id(): string;

    /**
     * Human readable, translated title shown above the chart.
     */
    public function get_title(): string;

    /**
     * Short translated explanation shown under the title.
     */
    public function get_description(): string;

    /**
     * Builds and returns the chart for this insight.
     *
     * @param int $courseid
     * @return \core\chart_base
     */
    public function build_chart(int $courseid): \core\chart_base;
}
