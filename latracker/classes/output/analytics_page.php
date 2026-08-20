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
use local_latracker\analytics_engine;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderable/templatable data for the Learning Analytics page (analytics.php).
 *
 * Loops over analytics_engine::get_providers() so new insights show up
 * automatically without touching this class or its template.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics_page implements renderable, templatable {

    protected int $courseid;

    public function __construct(int $courseid) {
        $this->courseid = $courseid;
    }

    public function export_for_template(renderer_base $output): array {
        // Chart rendering (render_chart_base()) is implemented on core_renderer,
        // not on renderer_base/plugin_renderer_base, so the global core $OUTPUT
        // is used here rather than the plugin renderer passed in by the caller.
        global $OUTPUT;

        $insights = [];

        foreach (analytics_engine::get_providers() as $provider) {
            $chart = $provider->build_chart($this->courseid);
            $haschartdata = !empty($chart->get_series());
            $insights[] = [
                'id'           => $provider->get_id(),
                'title'        => $provider->get_title(),
                'description'  => $provider->get_description(),
                // core_renderer::render_chart_base() outputs ready-to-use HTML
                // and wires up the core/chart_output_chartjs AMD module
                // itself, so no custom client-side chart JS is needed here.
                'charthtml'    => $haschartdata ? $OUTPUT->render($chart) : '',
                'haschartdata' => $haschartdata,
            ];
        }

        return [
            'courseid' => $this->courseid,
            'insights' => $insights,
            'hasinsights' => !empty($insights),
        ];
    }
}
