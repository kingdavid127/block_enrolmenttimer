<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_enrolmenttimer\task;

use context_course;
use core\task\scheduled_task;
use core_user;
use stdClass;

/**
 * Sends enrolment expiry warnings and course completion notifications.
 *
 * Note on the block_enrolmenttimer table: its `enrolid` column holds a
 * user_enrolments.id, not an enrol.id, despite the name. Renaming it is a
 * schema change left for a separate patch, so every query here joins it to
 * {user_enrolments}.id.
 *
 * @package   block_enrolmenttimer
 * @copyright LearningWorks Ltd 2016
 * @copyright 2026 Dragonfly EdTech
 * @author    David Saylor <david.saylor@dragonflyedtech.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolmenttimer_task extends scheduled_task {
    /** @var array Course cache for the life of one run. */
    private array $courses = [];

    /**
     * Name shown on the scheduled tasks admin screen.
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'block_enrolmenttimer');
    }

    /**
     * Run the task.
     */
    public function execute() {
        $sendalerts = (bool) get_config('block_enrolmenttimer', 'timeleftmessagechk');
        $sendcompletions = (bool) get_config('block_enrolmenttimer', 'completionsmessagechk');

        if (!$sendalerts && !$sendcompletions) {
            mtrace('Neither notification is enabled, nothing to do.');
            return;
        }

        $courseids = $this->get_course_ids_with_block();
        if (!$courseids) {
            mtrace('No courses contain the enrolment timer block, nothing to do.');
            return;
        }
        mtrace('Found ' . count($courseids) . ' course(s) with the block.');

        if ($sendalerts) {
            $this->purge_orphan_alerts();
            $this->queue_alerts($courseids);
            $this->send_due_alerts();
        }

        if ($sendcompletions) {
            $this->send_completion_notifications($courseids);
        }
    }

    /**
     * Course ids that have at least one instance of this block.
     *
     * @return array
     */
    private function get_course_ids_with_block(): array {
        global $DB;

        $sql = "SELECT DISTINCT ctx.instanceid
                  FROM {block_instances} bi
                  JOIN {context} ctx ON ctx.id = bi.parentcontextid
                                        AND ctx.contextlevel = :courselevel
                 WHERE bi.blockname = :blockname";

        return $DB->get_fieldset_sql($sql, [
            'courselevel' => CONTEXT_COURSE,
            'blockname' => 'enrolmenttimer',
        ]);
    }

    /**
     * Drop queued alerts whose user enrolment has since been removed.
     */
    private function purge_orphan_alerts(): void {
        global $DB;

        $sql = "SELECT bet.id
                  FROM {block_enrolmenttimer} bet
             LEFT JOIN {user_enrolments} ue ON ue.id = bet.enrolid
                 WHERE ue.id IS NULL";

        $ids = $DB->get_fieldset_sql($sql);
        if ($ids) {
            $DB->delete_records_list('block_enrolmenttimer', 'id', $ids);
            mtrace('Removed ' . count($ids) . ' orphaned alert record(s).');
        }
    }

    /**
     * Record an alert time for any enrolment that has an end date and is not queued yet.
     *
     * All enrolment methods are considered, and the user enrolment's own end date
     * takes precedence over the enrolment method's.
     *
     * @param array $courseids
     */
    private function queue_alerts(array $courseids): void {
        global $DB;

        $days = (int) get_config('block_enrolmenttimer', 'daystoalertenrolmentend');
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');

        $params['uestatus'] = ENROL_USER_ACTIVE;
        $params['estatus'] = ENROL_INSTANCE_ENABLED;

        $sql = "SELECT ue.id, ue.timeend, e.enrolenddate
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
             LEFT JOIN {block_enrolmenttimer} bet ON bet.enrolid = ue.id
                 WHERE e.courseid $insql
                       AND ue.status = :uestatus
                       AND e.status = :estatus
                       AND bet.id IS NULL
                       AND (ue.timeend > 0 OR e.enrolenddate > 0)";

        $records = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $record) {
            $end = (int) ($record->timeend ?: $record->enrolenddate);
            if ($end <= 0) {
                continue;
            }
            $records[] = (object) [
                'enrolid' => (int) $record->id,
                'alerttime' => $end - ($days * DAYSECS),
                'sent' => 0,
            ];
        }
        $rs->close();

        if (!$records) {
            return;
        }

        $DB->insert_records('block_enrolmenttimer', $records);
        mtrace('Queued ' . count($records) . ' enrolment expiry alert(s).');
    }

    /**
     * Send any queued alert whose alert time has passed.
     */
    private function send_due_alerts(): void {
        global $DB;

        $sql = "SELECT bet.id, ue.userid, e.courseid
                  FROM {block_enrolmenttimer} bet
                  JOIN {user_enrolments} ue ON ue.id = bet.enrolid
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE bet.sent = 0 AND bet.alerttime <= :now";

        $days = (string) (int) get_config('block_enrolmenttimer', 'daystoalertenrolmentend');

        $sent = 0;
        $rs = $DB->get_recordset_sql($sql, ['now' => time()]);
        foreach ($rs as $record) {
            $user = $this->get_user((int) $record->userid);
            $course = $this->get_course((int) $record->courseid);
            if (!$user || !$course) {
                continue;
            }

            $success = $this->send_notification(
                $user,
                $course,
                'enrolmentemailsubject',
                'emailsubject_expiring_default',
                'timeleftmessage',
                [
                    '[[days_to_alert]]' => $days,
                ]
            );

            if ($success) {
                $DB->set_field('block_enrolmenttimer', 'sent', 1, ['id' => $record->id]);
                $sent++;
            }
        }
        $rs->close();

        mtrace('Sent ' . $sent . ' enrolment expiry alert(s).');
    }

    /**
     * Notify users who have completed a course since the previous run.
     *
     * @param array $courseids
     */
    private function send_completion_notifications(array $courseids): void {
        global $DB;

        $lastrun = (int) $this->get_last_run_time();
        if ($lastrun <= 0) {
            mtrace('First run, skipping historic course completions.');
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $params['lastrun'] = $lastrun;

        $sql = "SELECT cc.id, cc.userid, cc.course
                  FROM {course_completions} cc
                 WHERE cc.course $insql AND cc.timecompleted > :lastrun";

        $sent = 0;
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $record) {
            $user = $this->get_user((int) $record->userid);
            $course = $this->get_course((int) $record->course);
            if (!$user || !$course) {
                continue;
            }
            if (
                $this->send_notification(
                    $user,
                    $course,
                    'completionemailsubject',
                    'emailsubject_completion_default',
                    'completionsmessage'
                )
            ) {
                $sent++;
            }
        }
        $rs->close();

        mtrace('Sent ' . $sent . ' course completion notification(s).');
    }

    /**
     * Send one notification email.
     *
     * @param stdClass $user recipient
     * @param stdClass $course course the notification is about
     * @param string $subjectsetting config name holding the subject
     * @param string $subjectdefault lang string used when the subject is blank
     * @param string $bodysetting config name holding the HTML body
     * @param array $extra additional placeholder => replacement pairs
     * @return bool whether the mail was accepted
     */
    private function send_notification(
        stdClass $user,
        stdClass $course,
        string $subjectsetting,
        string $subjectdefault,
        string $bodysetting,
        array $extra = []
    ): bool {
        $body = (string) get_config('block_enrolmenttimer', $bodysetting);
        if (trim($body) === '') {
            // Nothing configured to send.
            return false;
        }

        $subject = trim((string) get_config('block_enrolmenttimer', $subjectsetting));
        if ($subject === '') {
            $subject = get_string($subjectdefault, 'block_enrolmenttimer');
        }

        $replacements = [
            '[[user_name]]' => $user->firstname,
            '[[course_name]]' => $course->fullname,
        ] + $extra;

        $body = str_replace(array_keys($replacements), array_values($replacements), $body);
        $html = format_text($body, FORMAT_HTML, ['context' => context_course::instance($course->id)]);

        return (bool) email_to_user(
            $user,
            core_user::get_support_user(),
            $subject,
            html_to_text($html),
            $html
        );
    }

    /**
     * Load a mailable user, or null if they cannot be mailed.
     *
     * @param int $userid
     * @return stdClass|null
     */
    private function get_user(int $userid): ?stdClass {
        $user = core_user::get_user($userid, '*', IGNORE_MISSING);
        if (!$user || $user->deleted || $user->suspended) {
            return null;
        }

        return $user;
    }

    /**
     * Load a course, cached for the life of this run.
     *
     * @param int $courseid
     * @return stdClass|null
     */
    private function get_course(int $courseid): ?stdClass {
        global $DB;

        if (!array_key_exists($courseid, $this->courses)) {
            $this->courses[$courseid] = $DB->get_record('course', ['id' => $courseid]) ?: null;
        }

        return $this->courses[$courseid];
    }
}
