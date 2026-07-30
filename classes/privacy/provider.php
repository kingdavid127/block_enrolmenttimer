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

namespace block_enrolmenttimer\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API provider.
 *
 * The plugin records, per user enrolment, when an expiry warning should be sent
 * and whether it has been. That is personal data tied to a course, so this is a
 * real provider rather than the null provider it used to claim to be.
 *
 * The `enrolid` column holds a user_enrolments.id despite its name.
 *
 * @package    block_enrolmenttimer
 * @copyright  2019 onwards LearningWorks Ltd {@link https://learningworks.co.nz/}
 * @copyright  2026 Dragonfly EdTech
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_enrolmenttimer', [
            'enrolid' => 'privacy:metadata:block_enrolmenttimer:enrolid',
            'alerttime' => 'privacy:metadata:block_enrolmenttimer:alerttime',
            'sent' => 'privacy:metadata:block_enrolmenttimer:sent',
        ], 'privacy:metadata:block_enrolmenttimer');

        return $collection;
    }

    /**
     * Courses in which we hold data for this user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {block_enrolmenttimer} bet
                  JOIN {user_enrolments} ue ON ue.id = bet.enrolid
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {context} ctx ON ctx.instanceid = e.courseid
                                        AND ctx.contextlevel = :courselevel
                 WHERE ue.userid = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'courselevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Users for whom we hold data in the given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $sql = "SELECT ue.userid
                  FROM {block_enrolmenttimer} bet
                  JOIN {user_enrolments} ue ON ue.id = bet.enrolid
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid";

        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * Export the data we hold for a user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }

            $sql = "SELECT bet.id, bet.alerttime, bet.sent
                      FROM {block_enrolmenttimer} bet
                      JOIN {user_enrolments} ue ON ue.id = bet.enrolid
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE e.courseid = :courseid AND ue.userid = :userid";

            $records = $DB->get_records_sql($sql, [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);

            if (!$records) {
                continue;
            }

            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'alerttime' => $record->alerttime ? transform::datetime($record->alerttime) : null,
                    'sent' => transform::yesno($record->sent),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_enrolmenttimer')],
                (object) ['alerts' => $data]
            );
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        if (!$context instanceof context_course) {
            return;
        }

        self::delete_rows((int) $context->instanceid);
    }

    /**
     * Delete all data for one user across the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            self::delete_rows((int) $context->instanceid, [$userid]);
        }
    }

    /**
     * Delete data for several users in one context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $userids = $userlist->get_userids();
        if ($userids) {
            self::delete_rows((int) $context->instanceid, $userids);
        }
    }

    /**
     * Delete the rows held for a course, optionally narrowed to some users.
     *
     * @param int $courseid
     * @param array $userids empty means every user in the course
     */
    private static function delete_rows(int $courseid, array $userids = []): void {
        global $DB;

        $params = ['courseid' => $courseid];
        $usertest = '';

        if ($userids) {
            [$insql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
            $usertest = " AND ue.userid $insql";
            $params += $userparams;
        }

        $sql = "SELECT bet.id
                  FROM {block_enrolmenttimer} bet
                  JOIN {user_enrolments} ue ON ue.id = bet.enrolid
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid$usertest";

        $ids = $DB->get_fieldset_sql($sql, $params);
        if ($ids) {
            $DB->delete_records_list('block_enrolmenttimer', 'id', $ids);
        }
    }
}
