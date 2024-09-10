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

namespace local_recompletion;

/**
 * Reminder email test.
 *
 * @package    local_recompletion
 * @copyright  2024 Josemaria Bolanos <josemabol@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_recompletion\observer
 */
class reminder_email_test extends \advanced_testcase {

    /**
     * Set up recompletion for a given course.
     *
     * @param int $courseid Course ID.
     * @param array $config Recompletion config for a given course. If empty default values will be aplied.
     */
    protected function set_up_recompletion(int $courseid, array $config = []): void {
        global $DB;

        $DB->delete_records('local_recompletion_config', ['course' => $courseid]);

        $defaultconfig = [
            'recompletiontype' => 'schedule',
            'recompletionschedule' => '6 months',
            'nextresettime' => time() + 7*DAYSECS,
            'archivecompletiondata' => 0,
            'recompletionunenrolenable' => 0,
            'resetunenrolsuser' => 0,
            'deletegradedata' => 1,
            'recompletionemailenable' => 0,
            'reminderemailenable' => 0
        ];

        $config = array_merge($defaultconfig, $config);

        foreach ($config as $name => $value) {
            $DB->insert_record('local_recompletion_config', (object) [
                'course' => $courseid,
                'name' => $name,
                'value' => $value,
            ]);
        }
    }

    /**
     * Test reminder email is sent after a user is un-enrolled based on course settings.
     */
    public function test_reminder_email() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $enrol = enrol_get_plugin('self');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $enrolinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        // Enrol four users to a course.
        $enrol->enrol_user($enrolinstance, $user1->id, $studentrole->id);
        $enrol->enrol_user($enrolinstance, $user2->id, $studentrole->id);
        $enrol->enrol_user($enrolinstance, $user3->id, $studentrole->id);

        // Test scheduled recompletion.
        $this->set_up_recompletion($course->id, []);

        // Catch the emails.
        $sink = $this->redirectEmails();

        // Complete the course for user 1.
        $compluser1 = new \completion_completion(['userid' => $user1->id, 'course' => $course->id]);
        $compluser1->mark_complete(time() - 2*WEEKSECS);
        $compluser1 = new \completion_completion(['userid' => $user1->id, 'course' => $course->id]);
        $this->assertTrue($compluser1->is_complete());

        // Clear the email sink.
        $sink->clear();

        // Run scheduled task.
        $task = new \local_recompletion\task\check_recompletion();
        $task->execute();

        // Check that exactly no emails were sent.
        $this->assertSame(0, $sink->count());
        $sink->clear();

        // Test scheduled recompletion.
        $subject = 'Your certificate is about to expire';
        $body = 'Your certificate will expire in 5 days. Please re-enrol to keep it active.';
        $this->set_up_recompletion($course->id, [
            'recompletiontype' => 'period',
            'recompletionduration' => YEARSECS,
            'recompletionunenrolenable' => 1,
            'reminderemailenable' => 1,
            'reminderemaildays' => 15*DAYSECS,
            'reminderemailsubject' => $subject,
            'reminderemailbody' => $body
        ]);

        // Complete the course for user 2. Out of the reminder period.
        $compluser2 = new \completion_completion(['userid' => $user2->id, 'course' => $course->id]);
        $compluser2->mark_complete(time() - YEARSECS + 15*DAYSECS);
        $compluser2 = new \completion_completion(['userid' => $user2->id, 'course' => $course->id]);
        $this->assertTrue($compluser2->is_complete());

        // Complete the course for user 3. In the reminder period.
        $compluser3 = new \completion_completion(['userid' => $user3->id, 'course' => $course->id]);
        $compluser3->mark_complete(time() - YEARSECS + 5*DAYSECS);
        $compluser3 = new \completion_completion(['userid' => $user3->id, 'course' => $course->id]);
        $this->assertTrue($compluser3->is_complete());

        // Clear the email sink.
        $sink->clear();

        // Run scheduled task.
        $task = new \local_recompletion\task\check_recompletion();
        $task->execute();

        // Check that exactly one email was sent.
        $this->assertSame(1, $sink->count());
        $result = $sink->get_messages();

        // Check the email content.
        $this->assertSame($subject, $result[0]->subject);
        $this->assertStringContainsString($body, quoted_printable_decode($result[0]->body));
        $this->assertSame($user3->email, $result[0]->to);

        // Check the log record.
        $logs = $DB->get_records('local_recompletion_log');
        $this->assertSame(1, count($logs));

        // Unenrol users 2 and 3.
        $enrol->unenrol_user($enrolinstance, $user2->id);
        $enrol->unenrol_user($enrolinstance, $user3->id);

        // Clear the email sink.
        $sink->clear();

        // Test scheduled recompletion.
        $this->set_up_recompletion($course->id, [
            'reminderemailenable' => 1,
            'reminderemaildays' => 10*DAYSECS,
            'reminderemailsubject' => $subject,
            'reminderemailbody' => $body
        ]);

        // Run scheduled task.
        $task = new \local_recompletion\task\check_recompletion();
        $task->execute();

        $result = $sink->get_messages();
        // Check that exactly one emails was sent.
        $this->assertSame(1, $sink->count());
        $result = $sink->get_messages();

        // Check the email content.
        $this->assertSame($subject, $result[0]->subject);
        $this->assertStringContainsString($body, quoted_printable_decode($result[0]->body));
        $this->assertSame($user1->email, $result[0]->to);

        // Run scheduled task.
        $task = new \local_recompletion\task\check_recompletion();
        $task->execute();

        // Check that email is only sent once.
        $this->assertSame(1, $sink->count());
        $result = $sink->get_messages();

        $sink->close();
    }

    /**
     * Test a user is un-enrolled based on course settings.
     */
    function test_user_is_unenrolled_based_on_settings() {
        global $CFG, $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // Catch the emails.
        $sink = $this->redirectEmails();

        // Complete the course for user.
        $compluser = new \completion_completion(['userid' => $user->id, 'course' => $course->id]);
        $this->assertFalse($compluser->is_complete());
        $compluser->mark_complete(time() - 2*WEEKSECS);
        $compluser = new \completion_completion(['userid' => $user->id, 'course' => $course->id]);
        $this->assertTrue($compluser->is_complete());

        // Test scheduled recompletion.
        $this->set_up_recompletion($course->id, [
            'recompletionunenrolenable' => 1,
            'resetunenrolsuser' => 1,
            'nextresettime' => time() - DAYSECS,
            'recompletionemailenable' => 1,
            'recompletionemailsubject' => 'Your certificate has expired',
            'recompletionemailbody' => 'Your certificate has expired. Please re-enrol to keep it active.',
            'reminderemailenable' => 1,
            'reminderemaildays' => 15*DAYSECS,
            'reminderemailsubject' => 'Your certificate is about to expire',
            'reminderemailbody' => 'Your certificate will expire in 15 days. Please re-enrol to keep it active.'
        ]);

        // Check that the user is enrolled
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user, '', true));

        // Clear the email sink.
        $sink->clear();

        // Run scheduled task.
        $task = new \local_recompletion\task\check_recompletion();
        $task->execute();

        // Check that email is only sent once.
        $this->assertSame(1, $sink->count());

        // Check that the user is unenrolled
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user, '', true));

        // Close the email sink.
        $sink->close();
    }
}
