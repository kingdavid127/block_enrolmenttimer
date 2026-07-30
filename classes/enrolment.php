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

/**
 * Works out when a user's access to a course ends, and how long that leaves.
 *
 * Replaces the old locallib.php. Units are identified by machine name
 * throughout so that the rendered markup and the JavaScript countdown do not
 * depend on the site language.
 *
 * @package   block_enrolmenttimer
 * @copyright LearningWorks Ltd 2016
 * @copyright 2026 Dragonfly EdTech
 * @author    David Saylor <david.saylor@dragonflyedtech.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment {
    /**
     * Units the block can display, largest first, in seconds.
     *
     * The order is significant: the remaining time is divided down through this
     * list. The keys are stable machine names and are also used as the config
     * values, the CSS classes and the data-id attributes read by the JS.
     */
    public const UNITS = [
        'years' => YEARSECS,
        'months' => 2592000,
        'weeks' => WEEKSECS,
        'days' => DAYSECS,
        'hours' => HOURSECS,
        'minutes' => MINSECS,
        'seconds' => 1,
    ];

    /**
     * Localised label for a unit, correctly singular or plural.
     *
     * @param string $unit one of the UNITS keys
     * @param int $count how many of them
     * @return string
     */
    public static function get_unit_label(string $unit, int $count): string {
        $key = 'unit_' . ($count === 1 ? rtrim($unit, 's') : $unit);

        return get_string($key, 'block_enrolmenttimer');
    }

    /**
     * The units an administrator has chosen to display.
     *
     * @param string|null $config comma separated unit machine names
     * @return array unit machine name => seconds, in canonical order
     */
    public static function get_selected_units(?string $config): array {
        if (empty($config)) {
            return self::UNITS;
        }

        $selected = array_filter(array_map('trim', explode(',', $config)));
        $units = array_intersect_key(self::UNITS, array_flip($selected));

        // An unrecognised or empty selection should still show something.
        return $units ?: self::UNITS;
    }

    /**
     * When the user's access to a course ends.
     *
     * All of the user's active enrolments are considered, not just self
     * enrolment, and the user enrolment's own end date takes precedence over the
     * enrolment method's. If any active enrolment has no end date at all then
     * access does not expire, whatever the other enrolments say.
     *
     * @param int $userid
     * @param int $courseid
     * @return int|null unix timestamp, or null if access does not expire
     */
    public static function get_access_end(int $userid, int $courseid): ?int {
        global $DB;

        $sql = "SELECT ue.id, ue.timeend, e.enrolenddate
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                       AND e.courseid = :courseid
                       AND ue.status = :uestatus
                       AND e.status = :estatus";

        $records = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'uestatus' => ENROL_USER_ACTIVE,
            'estatus' => ENROL_INSTANCE_ENABLED,
        ]);

        if (!$records) {
            return null;
        }

        $latest = 0;
        foreach ($records as $record) {
            $end = (int) ($record->timeend ?: $record->enrolenddate);
            if ($end <= 0) {
                // At least one enrolment never expires.
                return null;
            }
            $latest = max($latest, $end);
        }

        return $latest ?: null;
    }

    /**
     * Break the time remaining down into the configured units.
     *
     * @param int $userid
     * @param int $courseid
     * @param string|null $unitsconfig comma separated unit machine names
     * @return array|null list of ['unit' => string, 'count' => int], or null if
     *                    access does not expire or has already ended
     */
    public static function get_remaining(int $userid, int $courseid, ?string $unitsconfig): ?array {
        $end = self::get_access_end($userid, $courseid);
        if ($end === null) {
            return null;
        }

        $remaining = $end - time();
        if ($remaining <= 0) {
            return null;
        }

        return self::split_into_units($remaining, self::get_selected_units($unitsconfig));
    }

    /**
     * Divide a number of seconds down through a set of units.
     *
     * @param int $seconds
     * @param array $units unit machine name => seconds
     * @return array list of ['unit' => string, 'count' => int]
     */
    public static function split_into_units(int $seconds, array $units): array {
        $result = [];
        foreach ($units as $unit => $size) {
            $count = intdiv($seconds, $size);
            if (!$count && !$result) {
                // Skip leading units that are still zero.
                continue;
            }
            $result[] = ['unit' => $unit, 'count' => $count];
            $seconds -= $count * $size;
        }

        return $result;
    }
}
