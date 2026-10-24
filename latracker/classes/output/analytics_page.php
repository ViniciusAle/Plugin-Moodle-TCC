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
use local_latracker\analytics\engagement_timeline;
use local_latracker\analytics\submission_timing;

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

        $timeline = engagement_timeline::get_data($this->courseid);
        $submissiontiming = submission_timing::get_data($this->courseid);

        return [
            'courseid' => $this->courseid,
            'insights' => $insights,
            'hasinsights' => !empty($insights),
            'hasengagementdata' => $timeline['hasdata'],
            'engagementtimelinejson' => engagement_timeline::to_json($timeline),
            'hassubmissiontimingdata' => $submissiontiming['hasdata'],
            'submissiontimingjson' => submission_timing::to_json($submissiontiming),
        ] + $this->export_kpi_section($timeline, $submissiontiming)
          + $this->export_option_click_section()
          + $this->export_essay_section()
          + $this->export_student_log_section();
    }

    /**
     * Returns the 'essayactivities'/'hasessayactivities' template variables
     * for the "essay writing behaviour" section: the course's quizzes.
     * Essay questions only exist inside quizzes; which quizzes actually
     * have recorded writing data is found out by the dropdown's AJAX call.
     */
    private function export_essay_section(): array {
        $modinfo = get_fast_modinfo($this->courseid);
        $activities = [];

        foreach ($modinfo->get_instances_of('quiz') as $cm) {
            if ($cm->uservisible) {
                $activities[] = ['cmid' => $cm->id, 'name' => $cm->get_formatted_name()];
            }
        }

        return [
            'essayactivities' => $activities,
            'hasessayactivities' => !empty($activities),
        ];
    }

    /**
     * Returns the 4 "at a glance" numbers shown in the KPI row at the top
     * of the dashboard. Reuses the engagement timeline and submission
     * timing data already fetched by export_for_template() instead of
     * running further queries.
     */
    private function export_kpi_section(array $timeline, array $submissiontiming): array {
        $studentcount = count($timeline['students']);

        $totalminutes = 0.0;
        foreach ($timeline['students'] as $student) {
            $totalminutes += !empty($student['data']) ? end($student['data']) : 0;
        }
        $avgminutes = $studentcount > 0 ? (int) round($totalminutes / $studentcount) : 0;

        $activitycount = count($submissiontiming['activities']);

        // Bucket index 4 is "late" - see submission_timing::BUCKET_STRINGS/bucket_index().
        $latecount = 0;
        foreach ($submissiontiming['activities'] as $activity) {
            $latecount += $activity['data'][4] ?? 0;
        }

        return [
            'kpistudents' => $studentcount,
            'kpiavgminutes' => $avgminutes,
            'kpiactivities' => $activitycount,
            'kpilate' => $latecount,
            'haskpis' => $timeline['hasdata'] || $submissiontiming['hasdata'],
        ];
    }

    /**
     * Returns the 'courseenrolledusers'/'hascourseenrolledusers' template
     * variables for the "student navigation trail" section.
     *
     * Site admins are excluded, consistent with local_latracker_before_footer()
     * in lib.php: their own testing/administration activity is not meant
     * to be surfaced as student data.
     */
    private function export_student_log_section(): array {
        $context = \context_course::instance($this->courseid);
        $users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname', 'u.lastname, u.firstname');

        $students = [];
        foreach ($users as $user) {
            if (is_siteadmin($user->id)) {
                continue;
            }
            $students[] = ['id' => (int) $user->id, 'name' => fullname($user)];
        }

        return [
            'courseenrolledusers' => $students,
            'hascourseenrolledusers' => !empty($students),
        ];
    }

    /**
     * Returns the 'optionclickactivities'/'hasoptionclickactivities'
     * template variables for the "selected options" section.
     */
    private function export_option_click_section(): array {
        $activities = $this->get_option_click_activities();

        return [
            'optionclickactivities' => $activities,
            'hasoptionclickactivities' => !empty($activities),
        ];
    }

    /**
     * Lists the course's quiz and choice activities, for the "selected
     * options" chart's activity dropdown. Listed regardless of whether
     * clicks have been recorded for them yet - the dropdown itself
     * triggers the AJAX fetch that finds out.
     *
     * @return array<int, array{cmid:int, name:string}>
     */
    private function get_option_click_activities(): array {
        $modinfo = get_fast_modinfo($this->courseid);
        $activities = [];

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || !in_array($cm->modname, ['choice', 'quiz'], true)) {
                continue;
            }
            $activities[] = ['cmid' => $cm->id, 'name' => $cm->get_formatted_name()];
        }

        return $activities;
    }
}
