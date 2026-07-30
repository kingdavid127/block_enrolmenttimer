moodle-enrolmenttimer
=====================

Moodle Block - enrolmenttimer
Developed by - Aaron Leggett - LearningWorks Ltd
Maintained by - Dragonfly EdTech

This block provides the functionality to display the time a user has left in their enrolment period.
There are many settings to choose from to customise the way this is displayed to the user.
A notification can be sent on a set period before the enrolment expires advising the user that their enrolment is coming to an end.
Another email notification can be sent once a user completes the course.

The plugin has been developed with limited styling to enable the best possible base for theme overrides to align the design with your existing moodle theme.


REQUIREMENTS
============
- Moodle 5.1 (2025100600). MOODLE_501_STABLE is the branch CI builds against.
- PHP 8.2, 8.3 or 8.4


VERSION UPDATES
===============
Version 2026073000
- Target Moodle 5.1: `requires` raised to 2025100600 and `supported` declared
- Use the namespaced core classes (`core\output\html_writer`, `core\user`,
  `core\context\course`) rather than the legacy global aliases
- Mark inherited block and task methods with `#[\Override]`
- Tidy `db/access.php` and scope PHPUnit coverage to the code this plugin ships

Version 2026072900
- Replaced locallib.php with the autoloaded `block_enrolmenttimer\enrolment` class
- Countdown markup moved to a Mustache template; units keyed by machine name so
  the JS countdown no longer depends on the site language
- All enrolment methods are considered, not just self enrolment
- Real Privacy API provider in place of the previous null provider
- Settings moved from the bare `enrolmenttimer` plugin name to `block_enrolmenttimer`
- PHPUnit coverage added for the helper, block rendering, task and privacy provider

Version 2019091800
- Update JS to use AMD format
- Implement Privacy API
- TravisCI file updated
- Supporting docs & comments updated, upgrade file fixed

Version 2017083000
- Modify the alerttime notification so it is now stored in a log that is then processed
- Add in support for self-enrolment enrolment types. Done by looking at the current instance if no end time set check if 
the instance is a self enrolment and get the end date if set

Version 2017060900
- Change the define() to require() to prevent mismatch error

Version 2016122101
- Compliance with Moodle Travis CI, Moved from cron to Scheduled task, Tested against moodle 2.9, 3.0, 3.1.3+, 3.2

Version 2016060800
- Updated locallib to remove the dependency on CFG->prefix and update the version file. Checked against Moodle 3.0.4 & 3.1

Version 2015031914
- Updated SQL query to use Moodle's table prefix setting instead of expecting it to be 'mdl_'

Version 2015020200
- Updated background image URL's and checked 2.8 functionlaity

Version 2014061205
- Fixed up the functionality so the actual text value of each incremental
value is only set in one place. Code is more streamlined in this file
also.

- As per the request of 'German Valero', I have added the remaining
strings to the langfile for easy AMOS language conversion

Version 2014061101
- Function Names have been updated to align with the Moodle frankenstyle naming convention