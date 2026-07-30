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

namespace block_enrolmenttimer;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the rendered block markup.
 *
 * The JavaScript countdown depends on specific attributes being present, and the
 * settings that toggle parts of the markup are easy to break silently, so the
 * output is asserted directly rather than only the data behind it.
 *
 * @package   block_enrolmenttimer
 * @copyright 2026 Dragonfly EdTech
 * @author    David Saylor <david.saylor@dragonflyedtech.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_enrolmenttimer::class)]
final class render_test extends \advanced_testcase {
    /**
     * Render the block for a student whose enrolment ends at a known time.
     *
     * @param int $secondsleft how long the enrolment has to run
     * @return string the block's HTML
     */
    private function render_for_student(int $secondsleft): string {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $user->id,
            $course->id,
            'student',
            'manual',
            0,
            time() + $secondsleft
        );
        $this->setUser($user);

        $instance = $this->getDataGenerator()->create_block('enrolmenttimer', [
            'parentcontextid' => \context_course::instance($course->id)->id,
        ]);

        $PAGE->set_course($course);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);

        $block = block_instance('enrolmenttimer', $instance, $PAGE);

        return (string) $block->get_content()->text;
    }

    /**
     * Unit labels appear when the setting is on.
     *
     * Regression test: the template originally used Handlebars style {{#../x}}
     * parent paths, which Mustache does not understand, so the labels and their
     * wrapper silently never rendered no matter how the setting was configured.
     */
    public function test_unit_labels_render_when_enabled(): void {
        $this->resetAfterTest();

        set_config('displayunitlabels', 1, 'block_enrolmenttimer');
        set_config('viewoptions', 'days,hours', 'block_enrolmenttimer');

        $html = $this->render_for_student((3 * DAYSECS) + (4 * HOURSECS));

        $this->assertStringContainsString('block_enrolmenttimer-labelled', $html);
        $this->assertStringContainsString('class="numberTypeWrapper"', $html);
        $this->assertStringContainsString('class="unit-label" data-unit="days"', $html);
        $this->assertStringContainsString('class="unit-label" data-unit="hours"', $html);
    }

    /**
     * Unit labels are absent when the setting is off.
     */
    public function test_unit_labels_absent_when_disabled(): void {
        $this->resetAfterTest();

        set_config('displayunitlabels', 0, 'block_enrolmenttimer');
        set_config('viewoptions', 'days,hours', 'block_enrolmenttimer');

        $html = $this->render_for_student((3 * DAYSECS) + (4 * HOURSECS));

        $this->assertStringNotContainsString('block_enrolmenttimer-labelled', $html);
        $this->assertStringNotContainsString('unit-label', $html);
    }

    /**
     * The attributes the JavaScript countdown reads are all present.
     */
    public function test_javascript_contract_attributes(): void {
        $this->resetAfterTest();

        set_config('activecountdown', 1, 'block_enrolmenttimer');
        set_config('viewoptions', 'days,hours,minutes,seconds', 'block_enrolmenttimer');

        $html = $this->render_for_student((3 * DAYSECS) + (4 * HOURSECS) + 125);

        // The root must be marked live, or the JS never starts.
        $this->assertStringContainsString('block_enrolmenttimer-live', $html);

        foreach (['days', 'hours', 'minutes', 'seconds'] as $unit) {
            $this->assertStringContainsString('data-unit="' . $unit . '"', $html);
        }

        // Machine names, never translated labels, and both plural forms for the swap.
        $this->assertStringContainsString('data-label-singular="day"', $html);
        $this->assertStringContainsString('data-label-plural="days"', $html);
        $this->assertStringContainsString('class="timerNumChar" data-position="0"', $html);
    }

    /**
     * The countdown does not animate when the setting is off.
     */
    public function test_not_live_when_active_countdown_disabled(): void {
        $this->resetAfterTest();

        set_config('activecountdown', 0, 'block_enrolmenttimer');

        $html = $this->render_for_student(DAYSECS);

        $this->assertStringContainsString('block_enrolmenttimer-timer', $html);
        $this->assertStringNotContainsString('block_enrolmenttimer-live', $html);
    }

    /**
     * Single figures are padded to two digits when configured.
     */
    public function test_force_two_digits(): void {
        $this->resetAfterTest();

        set_config('forcetwodigits', 1, 'block_enrolmenttimer');
        set_config('viewoptions', 'days', 'block_enrolmenttimer');

        $html = $this->render_for_student(3 * DAYSECS);

        // Three days padded to "03" gives two character spans.
        $this->assertSame(2, substr_count($html, 'class="timerNumChar"'));
        $this->assertStringContainsString('data-position="1"', $html);
    }

    /**
     * Unpadded output has one span per real digit.
     */
    public function test_without_force_two_digits(): void {
        $this->resetAfterTest();

        set_config('forcetwodigits', 0, 'block_enrolmenttimer');
        set_config('viewoptions', 'days', 'block_enrolmenttimer');

        $html = $this->render_for_student(3 * DAYSECS);

        $this->assertSame(1, substr_count($html, 'class="timerNumChar"'));
    }

    /**
     * The text counter is visible when switched on.
     *
     * Each test renders only once, because the page's course cannot be changed
     * after the theme has been initialised for output.
     */
    public function test_text_counter_shown(): void {
        $this->resetAfterTest();
        set_config('viewoptions', 'days', 'block_enrolmenttimer');
        set_config('displaytextcounter', 1, 'block_enrolmenttimer');

        $html = $this->render_for_student(DAYSECS);

        $this->assertStringContainsString('text-desc', $html);
        $this->assertStringNotContainsString('d-none', $html);
    }

    /**
     * The text counter is hidden rather than omitted when switched off.
     */
    public function test_text_counter_hidden(): void {
        $this->resetAfterTest();
        set_config('viewoptions', 'days', 'block_enrolmenttimer');
        set_config('displaytextcounter', 0, 'block_enrolmenttimer');

        $html = $this->render_for_student(DAYSECS);

        $this->assertStringContainsString('text-desc', $html);
        $this->assertStringContainsString('d-none', $html);
    }

    /**
     * Only the configured units are rendered.
     */
    public function test_only_selected_units_render(): void {
        $this->resetAfterTest();

        set_config('viewoptions', 'days,hours', 'block_enrolmenttimer');

        $html = $this->render_for_student((3 * DAYSECS) + (4 * HOURSECS) + 125);

        $this->assertStringContainsString('data-unit="days"', $html);
        $this->assertStringContainsString('data-unit="hours"', $html);
        $this->assertStringNotContainsString('data-unit="minutes"', $html);
        $this->assertStringNotContainsString('data-unit="seconds"', $html);

        // One separator between two units.
        $this->assertSame(1, substr_count($html, 'class="seperator"'));
    }

    /**
     * A user whose enrolment never ends sees the configured message.
     */
    public function test_no_end_date_message(): void {
        global $PAGE;

        $this->resetAfterTest();
        set_config('displaynothingnodateset', 0, 'block_enrolmenttimer');

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $instance = $this->getDataGenerator()->create_block('enrolmenttimer', [
            'parentcontextid' => \context_course::instance($course->id)->id,
        ]);
        $PAGE->set_course($course);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);

        $html = (string) block_instance('enrolmenttimer', $instance, $PAGE)->get_content()->text;

        $this->assertStringContainsString(get_string('nodateset', 'block_enrolmenttimer'), $html);
    }

    /**
     * The block hides itself entirely when configured to do so.
     */
    public function test_hidden_when_no_end_date_and_configured_to_hide(): void {
        global $PAGE;

        $this->resetAfterTest();
        set_config('displaynothingnodateset', 1, 'block_enrolmenttimer');

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $instance = $this->getDataGenerator()->create_block('enrolmenttimer', [
            'parentcontextid' => \context_course::instance($course->id)->id,
        ]);
        $PAGE->set_course($course);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);

        $html = (string) block_instance('enrolmenttimer', $instance, $PAGE)->get_content()->text;

        $this->assertSame('', $html);
    }
}
