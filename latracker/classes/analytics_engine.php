<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_latracker;

use local_latracker\analytics\insight;
use local_latracker\analytics\engagement_insight;
use local_latracker\analytics\assignment_behavior_insight;
use local_latracker\analytics\csv_cross_insight;

defined('MOODLE_INTERNAL') || die();

/**
 * Registry of Learning Analytics insights shown on analytics.php.
 *
 * Adding a new insight requires only implementing local_latracker\analytics\insight
 * and appending it to the array below (or wiring it through a future
 * plugin hook/callback if third-party subplugins are ever needed) - no
 * changes to analytics.php or the template are necessary.
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
            new engagement_insight(),
            new assignment_behavior_insight(),
            new csv_cross_insight(),
        ];
    }
}
