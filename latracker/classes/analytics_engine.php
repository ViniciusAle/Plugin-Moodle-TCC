<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_latracker;

use local_latracker\analytics\insight;
use local_latracker\analytics\assignment_behavior_insight;
use local_latracker\analytics\click_engagement_insight;
use local_latracker\analytics\rage_click_insight;

defined('MOODLE_INTERNAL') || die();

/**
 * Registry of Learning Analytics insights shown on analytics.php.
 *
 * Adding a new insight requires only implementing local_latracker\analytics\insight
 * and appending it to the array below (or wiring it through a future
 * plugin hook/callback if third-party subplugins are ever needed) - no
 * changes to analytics.php or the template are necessary.
 *
 * Three charts are deliberately NOT part of this registry, because they
 * need direct access to their own Chart.js instance to support a filter
 * dropdown (student and/or activity) - they are built separately, see
 * analytics\engagement_timeline, analytics\submission_timing, the
 * local_latracker_get_option_clicks external function, and
 * output\analytics_page.
 *
 * csv_cross_insight is intentionally not registered here: the "Insights de
 * Learning Analytics" dashboard was streamlined (2026 UI refactor) to focus
 * on native tracking data only. The class itself is untouched, so it can be
 * added back to the list below at any time.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics_engine {

    /**
     * @return insight[]
     */
    public static function get_providers(): array {
        return [
            new click_engagement_insight(),
            new assignment_behavior_insight(),
            new rage_click_insight(),
        ];
    }
}
