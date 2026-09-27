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

declare(strict_types=1);

namespace customcertelement_idverify;

use local_idverify\event\idcode_decrypted;
use local_idverify\local\identity_manager;
use local_idverify\local\verified_person;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\service\pdf_generation_service;
use mod_customcert\template;
use stdClass;

/**
 * Tests for the Verified identity certificate element and the issued-certificate register.
 *
 * @package    customcertelement_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(element::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_idverify\observer::class)]
final class element_test extends \advanced_testcase {
    /** @var stdClass Certificate activity record. */
    private stdClass $customcert;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->local_idverify_hmackey = str_repeat('x', 48);
        $course = $this->getDataGenerator()->create_course();
        $this->customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
    }

    /**
     * Add an element to the certificate's template.
     *
     * @param string $type Element type.
     * @param array $data Element data.
     * @return stdClass Element record.
     */
    private function add_element(string $type = 'idverify', array $data = ['show' => element::SHOW_IDCODE]): stdClass {
        global $DB;
        $pageid = $DB->get_field(
            'customcert_pages',
            'id',
            ['templateid' => $this->customcert->templateid],
            IGNORE_MULTIPLE
        );
        if (!$pageid) {
            $pageid = $DB->insert_record('customcert_pages', (object)['templateid' => $this->customcert->templateid,
                'width' => 297, 'height' => 210, 'leftmargin' => 0, 'rightmargin' => 0, 'sequence' => 1,
                'timecreated' => time(), 'timemodified' => time()]);
        }
        $record = (object)['pageid' => $pageid, 'name' => $type, 'element' => $type,
            'data' => json_encode($data + ['font' => 'freesans', 'fontsize' => 12, 'colour' => '#000000', 'width' => 0]),
            'posx' => 10, 'posy' => 10, 'width' => 0, 'refpoint' => 0, 'alignment' => 'L', 'sequence' => 1,
            'timecreated' => time(), 'timemodified' => time()];
        $record->id = $DB->insert_record('customcert_elements', $record);
        return $record;
    }

    /**
     * Verify a user with a Smart-ID test identity.
     *
     * @param int $userid
     * @param string|null $code
     */
    private function verify(int $userid, ?string $code = '40404040009'): void {
        $method = $code === null ? verified_person::METHOD_MANUAL : verified_person::METHOD_SMARTID;
        identity_manager::verify($userid, new verified_person(
            'EE',
            $code,
            'Ok',
            'Test',
            '1904-04-04',
            $method,
            $code === null ? 'manual' : 'mock'
        ), $code === null ? 2 : null, $code === null ? 'Passport' : null);
    }

    /**
     * The code is printed for a verified user and the decryption is logged in the certificate's context.
     */
    public function test_idcode_for_verified_user(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->verify((int)$user->id);
        $element = new element($this->add_element());

        $sink = $this->redirectEvents();
        $this->assertSame('40404040009', $element->get_text($user, false));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof idcode_decrypted));
        $this->assertCount(1, $events);
        $this->assertEquals($user->id, $events[0]->relateduserid);
        $this->assertEquals(\context_module::instance($this->customcert->cmid)->id, $events[0]->contextid);
        $this->assertStringNotContainsString('40404040009', json_encode($events[0]->get_data()));
    }

    /**
     * The legal name option prints the stored legal name, not the account name.
     */
    public function test_legalname(): void {
        $user = $this->getDataGenerator()->create_user();
        set_config('overwritenames', '0', 'local_idverify');
        $this->verify((int)$user->id);
        $element = new element($this->add_element('idverify', ['show' => element::SHOW_LEGALNAME]));
        $this->assertSame('Ok Test', $element->get_text($user, false));
    }

    /**
     * Unverified users get nothing, with a developer debugging message.
     */
    public function test_unverified_user(): void {
        $user = $this->getDataGenerator()->create_user();
        $element = new element($this->add_element());
        $this->assertSame('', $element->get_text($user, false));
        $this->assertDebuggingCalled();
    }

    /**
     * Previews and the edit screen show a placeholder and decrypt nothing.
     */
    public function test_preview_placeholder(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->verify((int)$user->id);
        $element = new element($this->add_element());
        $sink = $this->redirectEvents();
        $this->assertSame(get_string('placeholder:idcode', 'customcertelement_idverify'), $element->get_text($user, true));
        $this->assertStringNotContainsString('40404040009', $element->render_html());
        $this->assertEmpty(array_filter($sink->get_events(), fn($e) => $e instanceof idcode_decrypted));
    }

    /**
     * A manual identity without a code prints the birth date instead.
     */
    public function test_birthdate_fallback(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->verify((int)$user->id, null);
        $element = new element($this->add_element());
        $this->assertSame('04.04.1904', $element->get_text($user, false));
    }

    /**
     * A real PDF renders with the element, and issuing registers the certificate for verified users only.
     */
    public function test_pdf_and_issue_register(): void {
        global $DB;
        $verified = $this->getDataGenerator()->create_user();
        $unverified = $this->getDataGenerator()->create_user();
        $this->verify((int)$verified->id);
        $this->add_element();

        $template = template::from_record($DB->get_record('customcert_templates', ['id' => $this->customcert->templateid]));
        $sink = $this->redirectEvents();
        $pdf = pdf_generation_service::create()->generate_pdf($template, false, (int)$verified->id, true);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertCount(1, array_filter($sink->get_events(), fn($e) => $e instanceof idcode_decrypted));
        $sink->close();

        certificate_issue_service::create()->issue_certificate((int)$this->customcert->id, (int)$verified->id);
        certificate_issue_service::create()->issue_certificate((int)$this->customcert->id, (int)$unverified->id);
        $issued = $DB->get_records('local_idverify_issued');
        $this->assertCount(1, $issued);
        $row = reset($issued);
        $this->assertEquals($verified->id, $row->userid);
        $this->assertEquals($this->customcert->id, $row->customcertid);
        $this->assertNotEmpty($row->code);
    }

    /**
     * Certificates whose template does not print the identity are not registered.
     */
    public function test_register_ignores_other_templates(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->verify((int)$user->id);
        $this->add_element('studentname', []);
        certificate_issue_service::create()->issue_certificate((int)$this->customcert->id, (int)$user->id);
        $this->assertSame(0, $DB->count_records('local_idverify_issued'));
    }

    /**
     * Form data normalisation and validation.
     */
    public function test_normalise_and_validate(): void {
        $element = new element($this->add_element());
        $data = $element->normalise_data((object)['show' => element::SHOW_LEGALNAME, 'fontsize' => '14']);
        $this->assertSame(element::SHOW_LEGALNAME, $data['show']);
        $this->assertSame(14, $data['fontsize']);
        $this->assertSame([], $element->validate(['show' => element::SHOW_IDCODE]));
        $this->assertArrayHasKey('show', $element->validate(['show' => 'email']));
    }
}
