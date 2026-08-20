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
 * Cross analysis between imported CSV data (e.g. an external quiz/survey
 * export) and the platform engagement time tracked for the same course.
 *
 * The CSV rows are stored generically as JSON (see csv_importer), so this
 * insight looks for a numeric-looking column to aggregate per file rather
 * than assuming a fixed schema - this keeps it usable for any CSV a
 * teacher might import.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_cross_insight implements insight {

    public function get_id(): string {
        return 'csvcross';
    }

    public function get_title(): string {
        return get_string('insight_csvcross_title', 'local_latracker');
    }

    public function get_description(): string {
        return get_string('insight_csvcross_desc', 'local_latracker');
    }

    public function build_chart(int $courseid): \core\chart_base {
        global $DB;

        $files = $DB->get_records('local_latracker_drivefile', ['courseid' => $courseid, 'status' => 'imported']);

        $labels = [];
        $rowcounts = [];
        $numericaverages = [];

        foreach ($files as $file) {
            $labels[] = $file->filename;
            $rowcounts[] = (int) $file->rowcount;
            $numericaverages[] = $this->average_first_numeric_column($file->id);
        }

        $chart = new \core\chart_bar();
        $chart->add_series(new \core\chart_series(get_string('importedrows', 'local_latracker'), $rowcounts));
        $chart->add_series(new \core\chart_series(get_string('avgnumericvalue', 'local_latracker'), $numericaverages));
        $chart->set_labels($labels);

        return $chart;
    }

    /**
     * Finds the first column across the file's rows whose values are
     * mostly numeric, and returns its average - a generic, schema-agnostic
     * way to surface "something interesting" from an arbitrary CSV.
     */
    private function average_first_numeric_column(int $fileid): float {
        global $DB;

        $rows = $DB->get_records('local_latracker_csvdata', ['fileid' => $fileid], 'rownumber ASC', 'id, rawdata', 0, 200);
        if (empty($rows)) {
            return 0.0;
        }

        $sums = [];
        $counts = [];
        foreach ($rows as $row) {
            $data = json_decode($row->rawdata, true) ?: [];
            foreach ($data as $key => $value) {
                if ($value === '' || !is_numeric($value)) {
                    continue;
                }
                $sums[$key] = ($sums[$key] ?? 0) + (float) $value;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        foreach ($sums as $key => $sum) {
            if ($counts[$key] >= count($rows) * 0.5) {
                return round($sum / $counts[$key], 2);
            }
        }

        return 0.0;
    }
}
