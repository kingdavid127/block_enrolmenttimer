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

/**
 * Admin settings.
 *
 * @package    block_enrolmenttimer
 * @copyright  LearningWorks Ltd 2016
 * @copyright  2026 Dragonfly EdTech
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_enrolmenttimer\enrolment;

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // General settings.
    $settings->add(new admin_setting_heading(
        'block_enrolmenttimer/headinggeneral',
        get_string('settings_general', 'block_enrolmenttimer'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_enrolmenttimer/displaynothingnodateset',
        get_string('displaynothingnodateset', 'block_enrolmenttimer'),
        get_string('displaynothingnodateset_help', 'block_enrolmenttimer'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_enrolmenttimer/displayunitlabels',
        get_string('displayunitlabels', 'block_enrolmenttimer'),
        get_string('displayunitlabels_help', 'block_enrolmenttimer'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_enrolmenttimer/forcetwodigits',
        get_string('forcetwodigits', 'block_enrolmenttimer'),
        get_string('forcetwodigits_help', 'block_enrolmenttimer'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_enrolmenttimer/displaytextcounter',
        get_string('displaytextcounter', 'block_enrolmenttimer'),
        get_string('displaytextcounter_help', 'block_enrolmenttimer'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_enrolmenttimer/activecountdown',
        get_string('activecountdown', 'block_enrolmenttimer'),
        get_string('activecountdown_help', 'block_enrolmenttimer'),
        1
    ));

    // Unit machine names are stored, not positions, so the selection survives a
    // change to the available units.
    $unitchoices = [];
    foreach (array_keys(enrolment::UNITS) as $unit) {
        $unitchoices[$unit] = enrolment::get_unit_label($unit, 2);
    }
    $settings->add(new admin_setting_configmultiselect(
        'block_enrolmenttimer/viewoptions',
        get_string('viewoptions', 'block_enrolmenttimer'),
        get_string('viewoptions_desc', 'block_enrolmenttimer'),
        array_keys($unitchoices),
        $unitchoices
    ));

    // Enrolment ending alert settings.
    $settings->add(new admin_setting_heading(
        'block_enrolmenttimer/headingalert',
        get_string('settings_notifications_alert', 'block_enrolmenttimer'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_enrolmenttimer/timeleftmessagechk',
        get_string('timeleftmessagechk', 'block_enrolmenttimer'),
        get_string('timeleftmessagechk_help', 'block_enrolmenttimer'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'block_enrolmenttimer/daystoalertenrolmentend',
        get_string('daystoalertenrolmentend', 'block_enrolmenttimer'),
        get_string('daystoalertenrolmentend_help', 'block_enrolmenttimer'),
        '10',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_enrolmenttimer/enrolmentemailsubject',
        get_string('emailsubject', 'block_enrolmenttimer'),
        get_string('emailsubject_help', 'block_enrolmenttimer'),
        get_string('emailsubject_expiring_default', 'block_enrolmenttimer')
    ));

    $settings->add(new admin_setting_confightmleditor(
        'block_enrolmenttimer/timeleftmessage',
        get_string('timeleftmessage', 'block_enrolmenttimer'),
        get_string('timeleftmessage_help', 'block_enrolmenttimer'),
        ''
    ));

    // Course completed message settings.
    $settings->add(new admin_setting_heading(
        'block_enrolmenttimer/headingcompletion',
        get_string('settings_notifications_completion', 'block_enrolmenttimer'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_enrolmenttimer/completionsmessagechk',
        get_string('completionsmessagechk', 'block_enrolmenttimer'),
        get_string('completionsmessagechk_help', 'block_enrolmenttimer'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'block_enrolmenttimer/completionemailsubject',
        get_string('emailsubject', 'block_enrolmenttimer'),
        get_string('emailsubject_help', 'block_enrolmenttimer'),
        get_string('emailsubject_completion_default', 'block_enrolmenttimer')
    ));

    $settings->add(new admin_setting_confightmleditor(
        'block_enrolmenttimer/completionsmessage',
        get_string('completionsmessage', 'block_enrolmenttimer'),
        get_string('completionsmessage_help', 'block_enrolmenttimer'),
        ''
    ));
}
