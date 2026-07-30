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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the enrolment timer scheduled task.
 *
 * @package   block_enrolmenttimer
 * @copyright 2026 Dragonfly EdTech
 * @author    David Saylor <david.saylor@dragonflyedtech.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(enrolmenttimer_task::class)]
final class enrolmenttimer_task_test extends \advanced_testcase {
    /**
     * The task class must be loadable and schedulable.
     *
     * This is the regression test for the malformed config.php require that used
     * to make autoloading this class fatal, and for the out of range hour in
     * db/tasks.php that made the task unschedulable.
     */
    public function test_task_is_registered_and_schedulable(): void {
        $task = \core\task\manager::get_scheduled_task(enrolmenttimer_task::class);

        $this->assertInstanceOf(enrolmenttimer_task::class, $task);
        $this->assertNotEmpty($task->get_name());
        $this->assertLessThan(\core\task\scheduled_task::NEVER_RUN_TIME, $task->get_next_scheduled_time());
    }

    /**
     * Set up a course containing the block, with one expiring student.
     *
     * The names are pinned to ASCII because the assertions below look for them
     * in a quoted-printable encoded mail body. The generator's default names are
     * deliberately non-ASCII, which is useful elsewhere but unhelpful here.
     *
     * @param int $timeend enrolment end date
     * @return array [course, user]
     */
    private function setup_course_with_block(int $timeend): array {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Astronomy']);
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
        ]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', 0, $timeend);

        $this->getDataGenerator()->create_block('enrolmenttimer', [
            'parentcontextid' => \core\context\course::instance($course->id)->id,
        ]);

        return [$course, $user];
    }

    /**
     * With both notifications off the task does nothing at all.
     */
    public function test_execute_does_nothing_when_disabled(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('timeleftmessagechk', 0, 'block_enrolmenttimer');
        set_config('completionsmessagechk', 0, 'block_enrolmenttimer');

        $this->setup_course_with_block(time() + DAYSECS);

        ob_start();
        (new enrolmenttimer_task())->execute();
        ob_end_clean();

        $this->assertSame(0, $DB->count_records('block_enrolmenttimer'));
    }

    /**
     * An expiring enrolment gets an alert row with the right alert time.
     */
    public function test_execute_queues_alert(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('timeleftmessagechk', 1, 'block_enrolmenttimer');
        set_config('daystoalertenrolmentend', 10, 'block_enrolmenttimer');
        set_config('timeleftmessage', '<p>Hello [[user_name]]</p>', 'block_enrolmenttimer');

        $timeend = time() + (30 * DAYSECS);
        [, $user] = $this->setup_course_with_block($timeend);

        ob_start();
        (new enrolmenttimer_task())->execute();
        ob_end_clean();

        $records = $DB->get_records('block_enrolmenttimer');
        $this->assertCount(1, $records);

        $record = reset($records);
        $this->assertSame($timeend - (10 * DAYSECS), (int) $record->alerttime);
        $this->assertSame(0, (int) $record->sent);

        // The row points at the user enrolment, not the enrol instance.
        $this->assertSame(
            (int) $user->id,
            (int) $DB->get_field('user_enrolments', 'userid', ['id' => $record->enrolid])
        );
    }

    /**
     * Running twice does not queue the same enrolment again.
     */
    public function test_execute_does_not_duplicate_alerts(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('timeleftmessagechk', 1, 'block_enrolmenttimer');
        set_config('timeleftmessage', 'body', 'block_enrolmenttimer');

        $this->setup_course_with_block(time() + (30 * DAYSECS));

        $task = new enrolmenttimer_task();
        ob_start();
        $task->execute();
        $task->execute();
        ob_end_clean();

        $this->assertSame(1, $DB->count_records('block_enrolmenttimer'));
    }

    /**
     * An enrolment with no end date is never queued.
     */
    public function test_execute_ignores_unlimited_enrolment(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('timeleftmessagechk', 1, 'block_enrolmenttimer');
        set_config('timeleftmessage', 'body', 'block_enrolmenttimer');

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->getDataGenerator()->create_block('enrolmenttimer', [
            'parentcontextid' => \core\context\course::instance($course->id)->id,
        ]);

        ob_start();
        (new enrolmenttimer_task())->execute();
        ob_end_clean();

        $this->assertSame(0, $DB->count_records('block_enrolmenttimer'));
    }

    /**
     * A course without the block is left alone.
     */
    public function test_execute_ignores_courses_without_the_block(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('timeleftmessagechk', 1, 'block_enrolmenttimer');
        set_config('timeleftmessage', 'body', 'block_enrolmenttimer');

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $user->id,
            $course->id,
            'student',
            'manual',
            0,
            time() + DAYSECS
        );

        ob_start();
        (new enrolmenttimer_task())->execute();
        ob_end_clean();

        $this->assertSame(0, $DB->count_records('block_enrolmenttimer'));
    }

    /**
     * A due alert is emailed and marked as sent, with placeholders substituted.
     */
    public function test_execute_sends_due_alert(): void {
        global $DB;

        $this->resetAfterTest();
        unset_config('noemailever');

        set_config('timeleftmessagechk', 1, 'block_enrolmenttimer');
        set_config('daystoalertenrolmentend', 10, 'block_enrolmenttimer');
        set_config('enrolmentemailsubject', 'Expiring soon', 'block_enrolmenttimer');
        set_config(
            'timeleftmessage',
            '<p>Hi [[user_name]], [[course_name]] ends in [[days_to_alert]] days.</p>',
            'block_enrolmenttimer'
        );

        // Ends in 5 days, alert threshold is 10 days, so it is already due.
        [$course, $user] = $this->setup_course_with_block(time() + (5 * DAYSECS));

        $sink = $this->redirectEmails();

        ob_start();
        (new enrolmenttimer_task())->execute();
        ob_end_clean();

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertSame($user->email, $messages[0]->to);
        $this->assertSame('Expiring soon', $messages[0]->subject);
        $this->assertStringContainsString($user->firstname, $messages[0]->body);
        $this->assertStringContainsString('10 days', $messages[0]->body);
        $this->assertStringContainsString('Astronomy', $messages[0]->body);
        $this->assertStringNotContainsString('[[user_name]]', $messages[0]->body);
        $this->assertStringNotContainsString('[[course_name]]', $messages[0]->body);

        $this->assertSame(1, $DB->count_records('block_enrolmenttimer', ['sent' => 1]));

        // A second run must not send it again.
        $sink = $this->redirectEmails();
        ob_start();
        (new enrolmenttimer_task())->execute();
        ob_end_clean();
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * An alert that is not yet due is queued but not sent.
     */
    public function test_execute_holds_alert_until_due(): void {
        global $DB;

        $this->resetAfterTest();
        unset_config('noemailever');

        set_config('timeleftmessagechk', 1, 'block_enrolmenttimer');
        set_config('daystoalertenrolmentend', 10, 'block_enrolmenttimer');
        set_config('timeleftmessage', 'body', 'block_enrolmenttimer');

        $this->setup_course_with_block(time() + (60 * DAYSECS));

        $sink = $this->redirectEmails();
        ob_start();
        (new enrolmenttimer_task())->execute();
        ob_end_clean();

        $this->assertCount(0, $sink->get_messages());
        $sink->close();
        $this->assertSame(1, $DB->count_records('block_enrolmenttimer', ['sent' => 0]));
    }

    /**
     * Nothing is sent when the administrator has left the message body empty.
     */
    public function test_execute_skips_empty_message_body(): void {
        $this->resetAfterTest();
        unset_config('noemailever');

        set_config('timeleftmessagechk', 1, 'block_enrolmenttimer');
        set_config('daystoalertenrolmentend', 10, 'block_enrolmenttimer');
        set_config('timeleftmessage', '', 'block_enrolmenttimer');

        $this->setup_course_with_block(time() + DAYSECS);

        $sink = $this->redirectEmails();
        ob_start();
        (new enrolmenttimer_task())->execute();
        ob_end_clean();

        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * Rows whose user enrolment has been deleted are cleaned up.
     */
    public function test_execute_purges_orphaned_alerts(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('timeleftmessagechk', 1, 'block_enrolmenttimer');
        set_config('timeleftmessage', 'body', 'block_enrolmenttimer');

        $this->setup_course_with_block(time() + (60 * DAYSECS));

        $orphan = $DB->insert_record('block_enrolmenttimer', (object) [
            'enrolid' => 99999999,
            'alerttime' => time(),
            'sent' => 0,
        ]);

        ob_start();
        (new enrolmenttimer_task())->execute();
        ob_end_clean();

        $this->assertFalse($DB->record_exists('block_enrolmenttimer', ['id' => $orphan]));
    }

    /**
     * The first ever run does not mail everybody who has already completed.
     */
    public function test_completion_notifications_skipped_on_first_run(): void {
        $this->resetAfterTest();
        unset_config('noemailever');

        set_config('completionsmessagechk', 1, 'block_enrolmenttimer');
        set_config('completionsmessage', 'Well done [[user_name]]', 'block_enrolmenttimer');

        [$course, $user] = $this->setup_course_with_block(0);
        $this->mark_completed($course->id, $user->id, time());

        $task = new enrolmenttimer_task();
        $task->set_last_run_time(0);

        $sink = $this->redirectEmails();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * A completion since the previous run is notified once.
     */
    public function test_completion_notification_sent_for_recent_completion(): void {
        $this->resetAfterTest();
        unset_config('noemailever');

        set_config('completionsmessagechk', 1, 'block_enrolmenttimer');
        set_config('completionemailsubject', 'Nice work', 'block_enrolmenttimer');
        set_config(
            'completionsmessage',
            '<p>Well done [[user_name]] on [[course_name]]</p>',
            'block_enrolmenttimer'
        );

        [$course, $user] = $this->setup_course_with_block(0);
        $this->mark_completed($course->id, $user->id, time() - 60);

        $task = new enrolmenttimer_task();
        $task->set_last_run_time(time() - HOURSECS);

        $sink = $this->redirectEmails();
        ob_start();
        $task->execute();
        ob_end_clean();

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertSame('Nice work', $messages[0]->subject);
        $this->assertStringContainsString($user->firstname, $messages[0]->body);
    }

    /**
     * A completion from before the previous run is not notified again.
     */
    public function test_completion_notification_not_resent(): void {
        $this->resetAfterTest();
        unset_config('noemailever');

        set_config('completionsmessagechk', 1, 'block_enrolmenttimer');
        set_config('completionsmessage', 'body', 'block_enrolmenttimer');

        [$course, $user] = $this->setup_course_with_block(0);
        $this->mark_completed($course->id, $user->id, time() - (2 * DAYSECS));

        $task = new enrolmenttimer_task();
        $task->set_last_run_time(time() - HOURSECS);

        $sink = $this->redirectEmails();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * Record a course completion directly.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $when
     */
    private function mark_completed(int $courseid, int $userid, int $when): void {
        global $DB;

        $DB->insert_record('course_completions', (object) [
            'userid' => $userid,
            'course' => $courseid,
            'timeenrolled' => $when - DAYSECS,
            'timestarted' => $when - DAYSECS,
            'timecompleted' => $when,
            'reaggregate' => 0,
        ]);
    }
}
