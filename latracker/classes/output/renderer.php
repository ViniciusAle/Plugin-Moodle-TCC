<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_latracker\output;

use plugin_renderer_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer for local_latracker.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    public function render_dashboard_page(dashboard_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_latracker/dashboard', $data);
    }

    public function render_analytics_page(analytics_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_latracker/analytics', $data);
    }
}
