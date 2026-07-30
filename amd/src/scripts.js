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
 * Live countdown for the enrolment timer block.
 *
 * Units are read from data-unit attributes holding machine names, so this works
 * regardless of the site language.
 *
 * @module     block_enrolmenttimer/scripts
 * @copyright  2014 onwards LearningWorks Ltd {@link https://learningworks.co.nz/}
 * @copyright  2026 Dragonfly EdTech
 * @author     Aaron Leggett
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Seconds in each unit, largest first. Must match \block_enrolmenttimer\enrolment::UNITS. */
const UNITS = [
    ['years', 31536000],
    ['months', 2592000],
    ['weeks', 604800],
    ['days', 86400],
    ['hours', 3600],
    ['minutes', 60],
    ['seconds', 1],
];

/** Seconds keyed by unit machine name. */
const SIZES = new Map(UNITS);

/**
 * Read the units this timer displays, largest first.
 *
 * @param {HTMLElement} timer The timer root element
 * @returns {Array} List of unit machine names
 */
const getDisplayedUnits = (timer) => {
    return Array.from(timer.querySelectorAll('.timer-wrapper .timerNum'))
        .map((node) => node.dataset.unit)
        .filter(Boolean);
};

/**
 * Total the displayed figures back into a number of seconds.
 *
 * @param {HTMLElement} timer The timer root element
 * @param {Array} units Unit machine names in play
 * @returns {number} Seconds remaining
 */
const readRemaining = (timer, units) => {
    return units.reduce((total, unit) => {
        const node = timer.querySelector('.timer-wrapper .timerNum[data-unit="' + unit + '"]');
        if (!node) {
            return total;
        }
        const digits = Array.from(node.querySelectorAll('.timerNumChar'))
            .map((span) => span.textContent.trim())
            .join('');
        const value = parseInt(digits, 10);

        return isNaN(value) ? total : total + (value * SIZES.get(unit));
    }, 0);
};

/**
 * Repaint one unit.
 *
 * @param {HTMLElement} timer The timer root element
 * @param {string} unit Unit machine name
 * @param {number} count How many of this unit remain
 * @param {boolean} forceTwoDigits Whether to pad single figures
 */
const paintUnit = (timer, unit, count, forceTwoDigits) => {
    const figures = timer.querySelector('.timer-wrapper .timerNum[data-unit="' + unit + '"]');
    const word = count === 1 ? 'labelSingular' : 'labelPlural';

    if (figures) {
        let text = String(count);
        if (forceTwoDigits && text.length === 1) {
            text = '0' + text;
        }
        figures.textContent = '';
        Array.from(text).forEach((digit, position) => {
            const span = document.createElement('span');
            span.className = 'timerNumChar';
            span.dataset.position = position;
            span.textContent = digit;
            figures.appendChild(span);
        });

        const label = timer.querySelector('.unit-label[data-unit="' + unit + '"]');
        if (label) {
            label.textContent = figures.dataset[word];
        }

        const textWord = timer.querySelector('.unit-word[data-unit="' + unit + '"]');
        if (textWord) {
            textWord.textContent = figures.dataset[word];
        }
    }

    const textCount = timer.querySelector('.unit-count[data-unit="' + unit + '"]');
    if (textCount) {
        textCount.textContent = count;
    }
};

/**
 * Start one timer ticking.
 *
 * @param {HTMLElement} timer The timer root element
 */
const startTimer = (timer) => {
    const units = getDisplayedUnits(timer);
    if (!units.length) {
        return;
    }

    const first = timer.querySelector('.timer-wrapper .timerNum');
    const forceTwoDigits = first !== null && first.querySelectorAll('.timerNumChar').length > 1;
    let remaining = readRemaining(timer, units);

    const handle = window.setInterval(() => {
        remaining -= 1;
        if (remaining <= 0) {
            remaining = 0;
            window.clearInterval(handle);
        }

        let left = remaining;
        UNITS.forEach(([unit, size]) => {
            if (units.indexOf(unit) === -1) {
                return;
            }
            const count = Math.floor(left / size);
            paintUnit(timer, unit, count, forceTwoDigits);
            left -= count * size;
        });
    }, 1000);
};

/**
 * Start every live timer on the page.
 */
export const init = () => {
    document.querySelectorAll('.block_enrolmenttimer-timer.block_enrolmenttimer-live')
        .forEach(startTimer);
};
