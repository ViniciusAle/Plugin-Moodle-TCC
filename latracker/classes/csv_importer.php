<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_latracker;

defined('MOODLE_INTERNAL') || die();

/**
 * Parses a CSV file (downloaded from Google Drive) and stores its rows.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_importer {

    /** @var int Hard cap so an oversized file cannot exhaust memory/DB. */
    const MAX_ROWS = 20000;

    /**
     * Imports one CSV file's raw text content into local_latracker_csvdata.
     *
     * @param int $courseid
     * @param int $userid Teacher performing the import.
     * @param string $drivefileid Google Drive file id.
     * @param string $filename
     * @param string $csvcontent Raw CSV text.
     * @return int The id of the created local_latracker_drivefile record.
     */
    public static function import(int $courseid, int $userid, string $drivefileid, string $filename, string $csvcontent): int {
        global $DB;

        $rows = self::parse($csvcontent);

        $filerecord = (object) [
            'courseid'     => $courseid,
            'userid'       => $userid,
            'drivefileid'  => $drivefileid,
            'filename'     => $filename,
            'status'       => empty($rows) ? 'empty' : 'imported',
            'rowcount'     => count($rows),
            'timeimported' => time(),
        ];
        $fileid = $DB->insert_record('local_latracker_drivefile', $filerecord);

        $now = time();
        $buffer = [];
        foreach ($rows as $index => $row) {
            $buffer[] = (object) [
                'fileid'      => $fileid,
                'rownumber'   => $index + 1,
                'rawdata'     => json_encode($row, JSON_UNESCAPED_UNICODE),
                'timecreated' => $now,
            ];

            // Insert in batches to keep memory usage predictable on large files.
            if (count($buffer) >= 500) {
                $DB->insert_records('local_latracker_csvdata', $buffer);
                $buffer = [];
            }
        }
        if (!empty($buffer)) {
            $DB->insert_records('local_latracker_csvdata', $buffer);
        }

        return $fileid;
    }

    /**
     * Parses CSV text into an array of associative rows keyed by header.
     *
     * @param string $csvcontent
     * @return array<int, array<string, string>>
     */
    public static function parse(string $csvcontent): array {
        $lines = preg_split("/\r\n|\r|\n/", trim($csvcontent));
        $lines = array_values(array_filter($lines, fn($l) => $l !== ''));

        if (empty($lines)) {
            return [];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map('trim', $header);

        $rows = [];
        foreach ($lines as $line) {
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
            $fields = str_getcsv($line);
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = $fields[$i] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
