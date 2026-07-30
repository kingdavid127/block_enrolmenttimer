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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the enrolment helper.
 *
 * @package   block_enrolmenttimer
 * @copyright 2026 Dragonfly EdTech
 * @author    David Saylor <david.saylor@dragonflyedtech.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(enrolment::class)]
final class enrolment_test extends \advanced_testcase {
    /**
     * The unit list must stay in descending order, or the breakdown is wrong.
     */
    public function test_units_are_descending(): void {
        $sizes = array_values(enrolment::UNITS);
        $sorted = $sizes;
        rsort($sorted);

        $this->assertSame($sorted, $sizes);
        $this->assertSame(1, end($sizes));
    }

    /**
     * Cases for the seconds to units breakdown.
     *
     * @return array
     */
    public static function split_provider(): array {
        return [
            'one of each' => [
                86400 + 3600 + 60 + 1,
                [['days', 1], ['hours', 1], ['minutes', 1], ['seconds', 1]],
            ],
            'leading zeroes suppressed' => [
                125,
                [['minutes', 2], ['seconds', 5]],
            ],
            'interior zeroes retained' => [
                86400 + 5,
                [['days', 1], ['hours', 0], ['minutes', 0], ['seconds', 5]],
            ],
            'one second' => [
                1,
                [['seconds', 1]],
            ],
            'zero' => [
                0,
                [],
            ],
        ];
    }

    /**
     * The breakdown keeps interior zeroes but drops leading ones.
     *
     * @param int $seconds
     * @param array $expected
     */
    #[DataProvider('split_provider')]
    public function test_split_into_units(int $seconds, array $expected): void {
        $result = enrolment::split_into_units($seconds, enrolment::UNITS);

        $actual = array_map(fn($entry) => [$entry['unit'], $entry['count']], $result);
        $this->assertSame($expected, $actual);
    }

    /**
     * The breakdown must always add back up to what went in.
     */
    public function test_split_into_units_is_lossless(): void {
        foreach ([1, 59, 3601, 86399, 90061, 31536000 + 12345] as $seconds) {
            $total = 0;
            foreach (enrolment::split_into_units($seconds, enrolment::UNITS) as $entry) {
                $total += $entry['count'] * enrolment::UNITS[$entry['unit']];
            }
            $this->assertSame($seconds, $total, "Round trip failed for $seconds seconds");
        }
    }

    /**
     * Only the selected units are used, and the canonical order is preserved.
     */
    public function test_get_selected_units(): void {
        // Deliberately out of order, to prove the canonical order wins.
        $selected = enrolment::get_selected_units('hours,days');
        $this->assertSame(['days', 'hours'], array_keys($selected));

        // Empty or unknown selections fall back to everything.
        $this->assertSame(array_keys(enrolment::UNITS), array_keys(enrolment::get_selected_units('')));
        $this->assertSame(array_keys(enrolment::UNITS), array_keys(enrolment::get_selected_units(null)));
        $this->assertSame(array_keys(enrolment::UNITS), array_keys(enrolment::get_selected_units('nonsense')));

        // Unknown names are dropped, known ones survive.
        $this->assertSame(['weeks'], array_keys(enrolment::get_selected_units('weeks,bogus')));
    }

    /**
     * Labels are singular for exactly one and plural otherwise.
     */
    public function test_get_unit_label(): void {
        $this->assertSame('day', enrolment::get_unit_label('days', 1));
        $this->assertSame('days', enrolment::get_unit_label('days', 0));
        $this->assertSame('days', enrolment::get_unit_label('days', 2));
        $this->assertSame('second', enrolment::get_unit_label('seconds', 1));
        $this->assertSame('year', enrolment::get_unit_label('years', 1));
    }

    /**
     * Every unit has both a singular and a plural string defined.
     */
    public function test_every_unit_has_labels(): void {
        foreach (array_keys(enrolment::UNITS) as $unit) {
            $this->assertNotEmpty(enrolment::get_unit_label($unit, 1), "Missing singular for $unit");
            $this->assertNotEmpty(enrolment::get_unit_label($unit, 2), "Missing plural for $unit");
        }
    }

    /**
     * A user with no enrolment at all has no end date.
     */
    public function test_get_access_end_not_enrolled(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->assertNull(enrolment::get_access_end($user->id, $course->id));
    }

    /**
     * An enrolment with no end date never expires.
     */
    public function test_get_access_end_unlimited_enrolment(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->assertNull(enrolment::get_access_end($user->id, $course->id));
    }

    /**
     * The user enrolment's own end date is used when set.
     */
    public function test_get_access_end_uses_user_enrolment_timeend(): void {
        $this->resetAfterTest();

        $end = time() + DAYSECS;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', 0, $end);

        $this->assertSame($end, enrolment::get_access_end($user->id, $course->id));
    }

    /**
     * The enrolment method's end date is used when the user enrolment has none.
     *
     * This also proves the old 'self' only restriction is gone, since the
     * enrolment here is manual.
     */
    public function test_get_access_end_falls_back_to_enrol_instance(): void {
        global $DB;

        $this->resetAfterTest();

        $end = time() + (5 * DAYSECS);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $DB->set_field('enrol', 'enrolenddate', $end, ['courseid' => $course->id, 'enrol' => 'manual']);

        $this->assertSame($end, enrolment::get_access_end($user->id, $course->id));
    }

    /**
     * Enable an enrolment method instance on a course.
     *
     * Courses get a self enrolment instance by default, but it is disabled, so
     * tests that need a second usable method have to switch it on.
     *
     * @param int $courseid
     * @param string $enrol plugin name
     */
    private function enable_enrol_instance(int $courseid, string $enrol): void {
        global $DB;

        $DB->set_field(
            'enrol',
            'status',
            ENROL_INSTANCE_ENABLED,
            ['courseid' => $courseid, 'enrol' => $enrol]
        );
    }

    /**
     * With several limited enrolments, the latest end date wins.
     */
    public function test_get_access_end_takes_latest_of_several(): void {
        $this->resetAfterTest();

        $soon = time() + DAYSECS;
        $later = time() + (10 * DAYSECS);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', 0, $soon);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'self', 0, $later);
        $this->enable_enrol_instance($course->id, 'self');

        $this->assertSame($later, enrolment::get_access_end($user->id, $course->id));
    }

    /**
     * One unlimited enrolment beats any number of limited ones.
     */
    public function test_get_access_end_unlimited_beats_limited(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', 0, time() + DAYSECS);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'self');
        $this->enable_enrol_instance($course->id, 'self');

        $this->assertNull(enrolment::get_access_end($user->id, $course->id));
    }

    /**
     * An enrolment through a disabled method does not grant access.
     *
     * Moodle adds a self enrolment instance to every course but leaves it
     * disabled, so this is the default state rather than an exotic one.
     */
    public function test_get_access_end_ignores_disabled_enrol_instance(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Unlimited, but through a method that is switched off.
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'self');

        $this->assertNull(enrolment::get_access_end($user->id, $course->id));

        // Once the method is enabled the same enrolment does count.
        $this->enable_enrol_instance($course->id, 'self');
        $this->assertNull(enrolment::get_access_end($user->id, $course->id));

        // And a limited one through that method is honoured.
        $end = time() + (7 * DAYSECS);
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $course->id, 'student', 'self', 0, $end);
        $this->assertSame($end, enrolment::get_access_end($other->id, $course->id));
    }

    /**
     * A suspended enrolment does not count.
     */
    public function test_get_access_end_ignores_suspended_enrolment(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $user->id,
            $course->id,
            'student',
            'manual',
            0,
            time() + DAYSECS,
            ENROL_USER_SUSPENDED
        );

        $this->assertNull(enrolment::get_access_end($user->id, $course->id));
    }

    /**
     * The remaining breakdown reflects the enrolment end date.
     */
    public function test_get_remaining(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $user->id,
            $course->id,
            'student',
            'manual',
            0,
            time() + (2 * DAYSECS) + 3600
        );

        $remaining = enrolment::get_remaining($user->id, $course->id, 'days,hours');

        $this->assertIsArray($remaining);
        $this->assertSame(['days', 'hours'], array_column($remaining, 'unit'));
        $this->assertSame(2, $remaining[0]['count']);
    }

    /**
     * An enrolment that has already ended reports nothing rather than a negative.
     */
    public function test_get_remaining_already_expired(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $user->id,
            $course->id,
            'student',
            'manual',
            0,
            time() - DAYSECS
        );

        $this->assertNull(enrolment::get_remaining($user->id, $course->id, null));
    }
}
