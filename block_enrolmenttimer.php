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
 * Main file to display the block.
 *
 * @package    block_enrolmenttimer
 * @copyright  LearningWorks Ltd 2016
 * @copyright  2026 Dragonfly EdTech
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_enrolmenttimer\enrolment;

/**
 * Shows how long the viewer has left on their enrolment.
 *
 * @package    block_enrolmenttimer
 * @copyright  LearningWorks Ltd 2016
 * @copyright  2026 Dragonfly EdTech
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_enrolmenttimer extends block_base {
    /** @var int Whether the JS countdown animates. */
    protected $activecountdown = 0;

    /** @var string|null Comma separated unit machine names to display. */
    protected $viewoptions = null;

    /**
     * Initialise the block.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_enrolmenttimer');
    }

    /**
     * This block has site level settings.
     *
     * @return bool
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Where the block may be added.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'site-index' => false,
            'course-view' => true,
            'course-view-social' => true,
            'mod' => true,
        ];
    }

    /**
     * Pull the site level settings this instance needs.
     */
    public function specialization(): void {
        $this->title = get_string('pluginname', 'block_enrolmenttimer');
        $this->activecountdown = (int) get_config('block_enrolmenttimer', 'activecountdown');
        $this->viewoptions = get_config('block_enrolmenttimer', 'viewoptions');
    }

    /**
     * Render the countdown.
     *
     * @return stdClass
     */
    public function get_content(): stdClass {
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Anyone who can administer the site has no meaningful enrolment end.
        if (has_capability('moodle/site:config', $this->context)) {
            return $this->content;
        }

        $remaining = enrolment::get_remaining($USER->id, $this->page->course->id, $this->viewoptions);

        if ($remaining === null) {
            if (!get_config('block_enrolmenttimer', 'displaynothingnodateset')) {
                $this->content->text = html_writer::tag(
                    'p',
                    get_string('nodateset', 'block_enrolmenttimer'),
                    ['class' => 'block_enrolmenttimer-nodateset']
                );
            }
            return $this->content;
        }

        $this->page->requires->js_call_amd('block_enrolmenttimer/scripts', 'init');
        $this->content->text = $this->render_timer($remaining);

        return $this->content;
    }

    /**
     * Build the timer markup from a list of remaining units.
     *
     * @param array $remaining list of ['unit' => string, 'count' => int]
     * @return string
     */
    protected function render_timer(array $remaining): string {
        global $OUTPUT;

        $forcetwodigits = (bool) get_config('block_enrolmenttimer', 'forcetwodigits');
        $lastindex = count($remaining) - 1;

        $units = [];
        foreach ($remaining as $index => $entry) {
            $figure = (string) $entry['count'];
            if ($forcetwodigits && strlen($figure) === 1) {
                $figure = '0' . $figure;
            }

            $digits = [];
            foreach (str_split($figure) as $position => $digit) {
                $digits[] = ['digit' => $digit, 'position' => $position];
            }

            $units[] = [
                'unit' => $entry['unit'],
                'count' => $entry['count'],
                'label' => enrolment::get_unit_label($entry['unit'], $entry['count']),
                'labelsingular' => enrolment::get_unit_label($entry['unit'], 1),
                'labelplural' => enrolment::get_unit_label($entry['unit'], 2),
                'digits' => $digits,
                'last' => $index === $lastindex,
            ];
        }

        return $OUTPUT->render_from_template('block_enrolmenttimer/timer', [
            'live' => $this->activecountdown === 1,
            'showlabels' => (bool) get_config('block_enrolmenttimer', 'displayunitlabels'),
            'showtext' => (bool) get_config('block_enrolmenttimer', 'displaytextcounter'),
            'expirytext' => get_string('expirytext', 'block_enrolmenttimer'),
            'units' => $units,
        ]);
    }
}
