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
 * Course recompletion settings form.
 *
 * @package     local_recompletion
 * @copyright   2017 Dan Marsden
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_recompletion_recompletion_form extends moodleform {
    /** @var string */
    const RECOMPLETION_TYPE_DISABLED = '';

    /** @var string */
    const RECOMPLETION_TYPE_PERIOD = 'period';

    /** @var string */
    const RECOMPLETION_TYPE_ONDEMAND = 'ondemand';

    /** @var string */
    const RECOMPLETION_TYPE_SCHEDULE = 'schedule';

    /**
     * Defines the form fields.
     */
    public function definition() {

        $mform = $this->_form;
        $course = $this->_customdata['course'];
        $instance = (object) ($this->_customdata['instance'] ?? []);
        $config = get_config('local_recompletion');

        $context = \context_course::instance($course->id);

        $editoroptions = [
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => 0,
            'changeformat' => 0,
            'context' => $context,
            'noclean' => 0,
            'trusttext' => 0,
            'cols' => '50',
            'rows' => '8',
        ];

        $mform->addElement('select', 'recompletiontype', get_string('recompletiontype', 'local_recompletion'), [
            self::RECOMPLETION_TYPE_DISABLED => get_string('recompletiontype:disabled', 'local_recompletion'),
            self::RECOMPLETION_TYPE_PERIOD => get_string('recompletiontype:period', 'local_recompletion'),
            self::RECOMPLETION_TYPE_ONDEMAND => get_string('recompletiontype:ondemand', 'local_recompletion'),
            self::RECOMPLETION_TYPE_SCHEDULE => get_string('recompletiontype:schedule', 'local_recompletion'),
        ]);
        $mform->setDefault('recompletiontype', self::RECOMPLETION_TYPE_DISABLED);
        $mform->addHelpButton('recompletiontype', 'recompletiontype', 'local_recompletion');

        $mform->addElement('checkbox', 'recompletionemailenable', get_string('recompletionemailenable', 'local_recompletion'));
        $mform->setDefault('recompletionemailenable', $config->recompletionemailenable);
        $mform->addHelpButton('recompletionemailenable', 'recompletionemailenable', 'local_recompletion');
        $mform->hideIf('recompletionemailenable', 'recompletiontype', 'eq', '');

        $mform->addElement('checkbox', 'reminderemailenable', get_string('reminderemailenable', 'local_recompletion'));
        $mform->setDefault('reminderemailenable', $config->reminderemailenable);
        $mform->addHelpButton('reminderemailenable', 'reminderemailenable', 'local_recompletion');
        $mform->hideIf('reminderemailenable', 'recompletiontype', 'eq', '');

        $mform->addElement('checkbox', 'recompletionunenrolenable', get_string('recompletionunenrolenable', 'local_recompletion'));
        $mform->setDefault('recompletionunenrolenable', $config->recompletionunenrolenable);
        $mform->addHelpButton('recompletionunenrolenable', 'recompletionunenrolenable', 'local_recompletion');
        $mform->hideIf('recompletionunenrolenable', 'recompletiontype', 'eq', '');

        $options = ['optional' => false, 'defaultunit' => 86400];
        $mform->addElement('duration', 'recompletionduration', get_string('recompletionrange', 'local_recompletion'), $options);
        $mform->addHelpButton('recompletionduration', 'recompletionrange', 'local_recompletion');
        $mform->setDefault('recompletionduration', $config->recompletionduration);
        $mform->hideif('recompletionduration', 'recompletiontype', 'neq', self::RECOMPLETION_TYPE_PERIOD);

        // Schedule / cron settings.
        $mform->addElement('text', 'recompletionschedule', get_string('recompletionschedule', 'local_recompletion'), 'size = "80"');
        $mform->setType('recompletionschedule', PARAM_TEXT);
        $mform->addHelpButton('recompletionschedule', 'recompletionschedule', 'local_recompletion');
        $mform->setDefault('recompletionschedule', $config->recompletionschedule ?? '');
        $mform->hideIf('recompletionschedule', 'recompletiontype', 'neq', 'schedule');
        $schedule = $this->_customdata['instance']['recompletionschedule'] ?? '';
        if (!empty($schedule)) {
            $calculated = local_recompletion_calculate_schedule_time($schedule);
            $formatted = userdate($calculated, get_string('strftimedatetime', 'langconfig'));
            $mform->addElement('static', 'calculatedtime', get_string('recompletioncalculateddate', 'local_recompletion', $formatted));
        }

        // Email Notification settings.
        $mform->addElement('header', 'emailheader', get_string('emailrecompletiontitle', 'local_recompletion'));
        $mform->setExpanded('emailheader', false);

        // Recompletion email settings.
        $mform->addElement('text', 'recompletionemailsubject', get_string('recompletionemailsubject', 'local_recompletion'),
                'size = "80"');
        $mform->setType('recompletionemailsubject', PARAM_TEXT);
        $mform->addHelpButton('recompletionemailsubject', 'recompletionemailsubject', 'local_recompletion');
        $mform->disabledIf('recompletionemailsubject', 'recompletiontype', 'eq', '');
        $mform->disabledIf('recompletionemailsubject', 'recompletionemailenable', 'notchecked');
        $mform->setDefault('recompletionemailsubject', $config->recompletionemailsubject);

        $mform->addElement('editor', 'recompletionemailbody', get_string('recompletionemailbody', 'local_recompletion'),
            $editoroptions);
        $mform->setDefault('recompletionemailbody', ['text' => $config->recompletionemailbody, 'format' => FORMAT_HTML]);
        $mform->addHelpButton('recompletionemailbody', 'recompletionemailbody', 'local_recompletion');
        $mform->disabledIf('recompletionemailbody', 'recompletiontype', 'eq', '');
        $mform->disabledIf('recompletionemailbody', 'recompletionemailenable', 'notchecked');

        // Reminder email settings.
        $mform->addElement('header', 'reminderemailheader', get_string('emailremindertitle', 'local_recompletion'));
        $mform->setExpanded('reminderemailheader', false);

        $options = ['optional' => false, 'defaultunit' => 86400];
        $mform->addElement('duration', 'reminderemaildays', get_string('reminderemaildays', 'local_recompletion'), $options);
        $mform->addHelpButton('reminderemaildays', 'reminderemaildays', 'local_recompletion');
        $mform->setDefault('reminderemaildays', $config->reminderemaildays);
        $mform->disabledIf('reminderemaildays', 'recompletiontype', 'eq', '');
        $mform->disabledIf('reminderemaildays', 'reminderemailenable', 'notchecked');

        $mform->addElement('text', 'reminderemailsubject', get_string('reminderemailsubject', 'local_recompletion'),
                'size = "80"');
        $mform->setType('reminderemailsubject', PARAM_TEXT);
        $mform->addHelpButton('reminderemailsubject', 'reminderemailsubject', 'local_recompletion');
        $mform->disabledIf('reminderemailsubject', 'recompletiontype', 'eq', '');
        $mform->disabledIf('reminderemailsubject', 'reminderemailenable', 'notchecked');
        $mform->setDefault('reminderemailsubject', $config->reminderemailsubject);

        $mform->addElement('editor', 'reminderemailbody', get_string('reminderemailbody', 'local_recompletion'),
            $editoroptions);
        $mform->setDefault('reminderemailbody', ['text' => $config->reminderemailbody, 'format' => FORMAT_HTML]);
        $mform->addHelpButton('reminderemailbody', 'reminderemailbody', 'local_recompletion');
        $mform->disabledIf('reminderemailbody', 'recompletiontype', 'eq', '');
        $mform->disabledIf('reminderemailbody', 'reminderemailenable', 'notchecked');

        // Advanced recompletion settings.
        // Delete data section.
        $mform->addElement('header', 'advancedheader', get_string('advancedrecompletiontitle', 'local_recompletion'));
        $mform->setExpanded('advancedheader', false);

        $mform->addElement('checkbox', 'resetunenrolsuser', get_string('resetunenrolsuser', 'local_recompletion'));
        $mform->setDefault('resetunenrolsuser', $config->resetunenrolsuser);
        $mform->addHelpButton('resetunenrolsuser', 'resetunenrolsuser', 'local_recompletion');

        $mform->addElement('checkbox', 'deletegradedata', get_string('deletegradedata', 'local_recompletion'));
        $mform->setDefault('deletegradedata', $config->deletegradedata);
        $mform->addHelpButton('deletegradedata', 'deletegradedata', 'local_recompletion');

        $mform->addElement('checkbox', 'archivecompletiondata', get_string('archivecompletiondata', 'local_recompletion'));
        // If we are forcing completion data archive, always be ticked.
        $archivedefault = $config->forcearchivecompletiondata ? 1 : $config->archivecompletiondata;
        $mform->setDefault('archivecompletiondata', $archivedefault);
        $mform->addHelpButton('archivecompletiondata', 'archivecompletiondata', 'local_recompletion');

        // Get all plugins that are supported.
        $plugins = local_recompletion_get_supported_plugins();
        foreach ($plugins as $plugin) {
            $fqn = 'local_recompletion\\plugins\\' . $plugin;
            $fqn::editingform($mform);
        }

        $mform->addElement('header', 'restrictionsheader', get_string('restrictionsheader', 'local_recompletion'));
        $restrictions = local_recompletion_get_supported_restrictions();
        foreach ($restrictions as $plugin) {
            $fqn = 'local_recompletion\\local\\restrictions\\' . $plugin;
            $fqn::editingform($mform);
        }
        $mform->disabledIf('resetunenrolsuser', 'recompletiontype', 'eq', '');
        $mform->disabledIf('deletegradedata', 'recompletiontype', 'eq', '');
        $mform->disabledIf('archivecompletiondata', 'recompletiontype', 'eq', '');
        $mform->disabledIf('archivecompletiondata', 'forcearchive', 'eq');

        // Add common action buttons.
        $this->add_action_buttons();

        // Add hidden fields.
        $mform->addElement('hidden', 'course', $course->id);
        $mform->setType('course', PARAM_INT);
        $mform->addElement('hidden', 'forcearchive', $config->forcearchivecompletiondata);
        $mform->setType('forcearchive', PARAM_BOOL);
    }

    /**
     * Validation function
     *
     * @param mixed $data
     * @param array $files
     * @return array
     **/
    public function validation($data, $files): array {
        $errors = [];

        // Validate 'recompletionschedule' field.
        if (!empty($data['recompletionschedule'])) {
            // Check if the input is compatible with strtotime().
            $value = local_recompletion_calculate_schedule_time($data['recompletionschedule']);
            if ($value === 0) {
                $errors['recompletionschedule'] = get_string('invalidscheduledate', 'local_recompletion');
            }
        }

        return $errors;
    }
}
