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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the privacy provider.
 *
 * @package   block_enrolmenttimer
 * @copyright 2026 Dragonfly EdTech
 * @author    David Saylor <david.saylor@dragonflyedtech.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Queue an alert row for a user in a course.
     *
     * @param int $courseid
     * @param int $userid
     * @return int inserted row id
     */
    private function queue_alert(int $courseid, int $userid): int {
        global $DB;

        $sql = "SELECT ue.id
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid AND ue.userid = :userid";
        $ueid = $DB->get_field_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

        return $DB->insert_record('block_enrolmenttimer', (object) [
            'enrolid' => $ueid,
            'alerttime' => time() + DAYSECS,
            'sent' => 0,
        ]);
    }

    /**
     * Create a course with an enrolled, expiring user and a queued alert.
     *
     * @return array [course, user]
     */
    private function setup_alert(): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $user->id,
            $course->id,
            'student',
            'manual',
            0,
            time() + (30 * DAYSECS)
        );
        $this->queue_alert($course->id, $user->id);

        return [$course, $user];
    }

    /**
     * The plugin declares the table it writes to.
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('block_enrolmenttimer');
        $collection = provider::get_metadata($collection);

        $types = $collection->get_collection();
        $this->assertCount(1, $types);
        $this->assertSame('block_enrolmenttimer', $types[0]->get_name());
    }

    /**
     * The user's course shows up, and other users' courses do not.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();

        [$course, $user] = $this->setup_alert();
        [, $otheruser] = $this->setup_alert();

        $contextlist = provider::get_contexts_for_userid($user->id);
        $contexts = $contextlist->get_contextids();

        $this->assertCount(1, $contexts);
        $this->assertSame(\context_course::instance($course->id)->id, (int) $contexts[0]);

        // The other user is in a different course, so must not appear here.
        $othercontexts = provider::get_contexts_for_userid($otheruser->id);
        $this->assertNotEquals($contexts, $othercontexts->get_contextids());
    }

    /**
     * A user with no queued alert has no contexts.
     */
    public function test_get_contexts_for_userid_none(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->assertCount(0, provider::get_contexts_for_userid($user->id)->get_contextids());
    }

    /**
     * Users are discoverable from the course context.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        [$course, $user] = $this->setup_alert();
        $context = \context_course::instance($course->id);

        $userlist = new userlist($context, 'block_enrolmenttimer');
        provider::get_users_in_context($userlist);

        $this->assertSame([(int) $user->id], array_map('intval', $userlist->get_userids()));
    }

    /**
     * A non course context yields nobody.
     */
    public function test_get_users_in_context_wrong_level(): void {
        $this->resetAfterTest();
        $this->setup_alert();

        $userlist = new userlist(\context_system::instance(), 'block_enrolmenttimer');
        provider::get_users_in_context($userlist);

        $this->assertCount(0, $userlist->get_userids());
    }

    /**
     * The queued alert is exported for the owning user.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();

        [$course, $user] = $this->setup_alert();
        $context = \context_course::instance($course->id);

        $this->export_context_data_for_user($user->id, $context, 'block_enrolmenttimer');

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data([get_string('pluginname', 'block_enrolmenttimer')]);
        $this->assertCount(1, $data->alerts);
        $this->assertSame('No', $data->alerts[0]->sent);
        $this->assertNotEmpty($data->alerts[0]->alerttime);
    }

    /**
     * Deleting a context removes its rows and leaves other courses alone.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();

        [$course] = $this->setup_alert();
        $this->setup_alert();
        $this->assertSame(2, $DB->count_records('block_enrolmenttimer'));

        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));

        $this->assertSame(1, $DB->count_records('block_enrolmenttimer'));
    }

    /**
     * Deleting for one user leaves their coursemate's row intact.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $user] = $this->setup_alert();

        $mate = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $mate->id,
            $course->id,
            'student',
            'manual',
            0,
            time() + (30 * DAYSECS)
        );
        $this->queue_alert($course->id, $mate->id);

        $this->assertSame(2, $DB->count_records('block_enrolmenttimer'));

        $context = \context_course::instance($course->id);
        provider::delete_data_for_user(new approved_contextlist(
            \core_user::get_user($user->id),
            'block_enrolmenttimer',
            [$context->id]
        ));

        $this->assertSame(1, $DB->count_records('block_enrolmenttimer'));
        $remaining = provider::get_contexts_for_userid($mate->id)->get_contextids();
        $this->assertCount(1, $remaining);
        $this->assertCount(0, provider::get_contexts_for_userid($user->id)->get_contextids());
    }

    /**
     * Bulk deletion removes only the approved users.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $user] = $this->setup_alert();

        $mate = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $mate->id,
            $course->id,
            'student',
            'manual',
            0,
            time() + (30 * DAYSECS)
        );
        $this->queue_alert($course->id, $mate->id);

        $context = \context_course::instance($course->id);
        provider::delete_data_for_users(new approved_userlist(
            $context,
            'block_enrolmenttimer',
            [$user->id]
        ));

        $this->assertSame(1, $DB->count_records('block_enrolmenttimer'));
        $this->assertCount(1, provider::get_contexts_for_userid($mate->id)->get_contextids());
    }
}
