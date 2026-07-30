<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade script
 *
 * @package    block_enrolmenttimer
 * @copyright  LearningWorks Ltd 2016
 * @copyright  2026 Dragonfly EdTech
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * When upgrading plugin, execute the following code.
 *
 * @param int $oldversion - previous version of plugin (from DB).
 */
function xmldb_block_enrolmenttimer_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2017083000) {
        // Define table block_enrolmenttimer to be created.
        $table = new xmldb_table('block_enrolmenttimer');

        // Adding fields to table block_enrolmenttimer.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('alerttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table block_enrolmenttimer.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for block_enrolmenttimer.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Enrolmenttimer savepoint reached.
        upgrade_block_savepoint(true, 2017083000, 'enrolmenttimer');
    }

    if ($oldversion < 2026072900) {
        // Settings used to live under the bare 'enrolmenttimer' plugin name,
        // which matches no installed component, so uninstall never cleaned them
        // up. Move them to 'block_enrolmenttimer' and lowercase the names.
        $renames = [
            'displayNothingNoDateSet' => 'displaynothingnodateset',
            'displayUnitLabels' => 'displayunitlabels',
            'forceTwoDigits' => 'forcetwodigits',
            'displayTextCounter' => 'displaytextcounter',
            'activecountdown' => 'activecountdown',
            'viewoptions' => 'viewoptions',
            'timeleftmessagechk' => 'timeleftmessagechk',
            'daystoalertenrolmentend' => 'daystoalertenrolmentend',
            'enrolmentemailsubject' => 'enrolmentemailsubject',
            'timeleftmessage' => 'timeleftmessage',
            'completionsmessagechk' => 'completionsmessagechk',
            'completionemailsubject' => 'completionemailsubject',
            'completionsmessage' => 'completionsmessage',
        ];

        foreach ($renames as $old => $new) {
            $value = get_config('enrolmenttimer', $old);
            if ($value !== false && get_config('block_enrolmenttimer', $new) === false) {
                set_config($new, $value, 'block_enrolmenttimer');
            }
            unset_config($old, 'enrolmenttimer');
        }

        // Never implemented, the setting had no effect on anything.
        unset_config('completionpercentage', 'enrolmenttimer');

        // The viewoptions setting stored positions into the unit list. Store
        // machine names instead so the selection survives a change to the
        // available units.
        $viewoptions = get_config('block_enrolmenttimer', 'viewoptions');
        if (!empty($viewoptions) && preg_match('/^[0-9,]+$/', $viewoptions)) {
            $units = array_keys(\block_enrolmenttimer\enrolment::UNITS);
            $names = [];
            foreach (explode(',', $viewoptions) as $position) {
                if (isset($units[(int) $position])) {
                    $names[] = $units[(int) $position];
                }
            }
            set_config('viewoptions', implode(',', $names), 'block_enrolmenttimer');
        }

        // The task looks this column up on every run.
        $table = new xmldb_table('block_enrolmenttimer');
        $index = new xmldb_index('enrolid', XMLDB_INDEX_NOTUNIQUE, ['enrolid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_block_savepoint(true, 2026072900, 'enrolmenttimer');
    }

    return true;
}
