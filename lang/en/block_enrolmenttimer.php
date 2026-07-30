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
 * Language strings for block_enrolmenttimer.
 *
 * @package    block_enrolmenttimer
 * @copyright  LearningWorks Ltd 2016
 * @copyright  2026 Dragonfly EdTech
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activecountdown'] = 'Actively count down';
$string['activecountdown_help'] = 'Actively count down the remaining time the student has to access the course using javascript';
$string['completionsmessage'] = 'Course Completion Email';
$string['completionsmessage_help'] = 'Email that will be sent congratulating the student on completing of the course. Here you can use the following customisations; [[user_name]] [[course_name]]';
$string['completionsmessagechk'] = 'Enable Completion Email';
$string['completionsmessagechk_help'] = 'Enables/Disables the completion email';
$string['daystoalertenrolmentend'] = 'Days to alert on';
$string['daystoalertenrolmentend_help'] = 'The amount of days before the enrollment ends at which to send the alert email';
$string['displaynothingnodateset'] = 'Hide block (No End Date Set)';
$string['displaynothingnodateset_help'] = 'Hides the block for users that have no end date set, If disabled, a message will be shown to those students';
$string['displaytextcounter'] = 'Display Text Counter';
$string['displaytextcounter_help'] = 'Displays the text counter that sits below the main counter';
$string['displayunitlabels'] = 'Display Unit Labels';
$string['displayunitlabels_help'] = 'Displays each unit below the main counter';
$string['emailsubject'] = 'Email Subject';
$string['emailsubject_completion_default'] = 'Course Completed';
$string['emailsubject_expiring_default'] = 'Enrolment Expiring';
$string['emailsubject_help'] = 'Subject of the email that will be sent to the user';
$string['enrolmenttimer:addinstance'] = 'Add a new Enrolment Timer block';
$string['expirytext'] = 'until your enrolment expires';
$string['forcetwodigits'] = 'Force 2 Digits';
$string['forcetwodigits_help'] = 'Forces the main countdown timer to always show atleast 2 digits (eg 01 hours left)';
$string['nodateset'] = 'Your enrolment does not expire';
$string['pluginname'] = 'Enrolment Timer';
$string['privacy:metadata:block_enrolmenttimer'] = 'Queued enrolment expiry warnings, so that each warning is only sent once.';
$string['privacy:metadata:block_enrolmenttimer:alerttime'] = 'The time at which the expiry warning becomes due.';
$string['privacy:metadata:block_enrolmenttimer:enrolid'] = 'The user enrolment the warning relates to.';
$string['privacy:metadata:block_enrolmenttimer:sent'] = 'Whether the warning has been emailed to the user.';
$string['settings_general'] = 'General Settings';
$string['settings_notifications_alert'] = 'Alert Email Notifications Settings';
$string['settings_notifications_completion'] = 'Completion Email Notifications Settings';
$string['timeleftmessage'] = 'Time Remaining Warning Message';
$string['timeleftmessage_help'] = 'Email that will be sent to the student advising how much time they have left on the course eg 10 days left. Here you can use the following customisations; [[user_name]] [[course_name]] [[days_to_alert]]';
$string['timeleftmessagechk'] = 'Enable Time Warning Email';
$string['timeleftmessagechk_help'] = 'Enables/Disables alert email';
$string['unit_day'] = 'day';
$string['unit_days'] = 'days';
$string['unit_hour'] = 'hour';
$string['unit_hours'] = 'hours';
$string['unit_minute'] = 'minute';
$string['unit_minutes'] = 'minutes';
$string['unit_month'] = 'month';
$string['unit_months'] = 'months';
$string['unit_second'] = 'second';
$string['unit_seconds'] = 'seconds';
$string['unit_week'] = 'week';
$string['unit_weeks'] = 'weeks';
$string['unit_year'] = 'year';
$string['unit_years'] = 'years';
$string['viewoptions'] = 'Increments Shown';
$string['viewoptions_desc'] = 'Select the increments to show in the block';
