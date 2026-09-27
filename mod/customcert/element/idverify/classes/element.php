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
use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * Prints the learner's verified personal ID code or legal name (from local_idverify).
 *
 * The code is decrypted only when a real certificate PDF is rendered (never for previews or the edit screen),
 * and each decryption is logged. customcert's public verification page does not render elements, so the code
 * never appears there.
 *
 * @package    customcertelement_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements
    form_element_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    validatable_element_interface
{
    /** Print the personal ID code (or the birth date for identities without one). */
    public const SHOW_IDCODE = 'idcode';
    /** Print the legal first and last name. */
    public const SHOW_LEGALNAME = 'legalname';

    #[\Override]
    public function build_form(MoodleQuickForm $mform): void {
        $mform->addElement('select', 'show', get_string('show', 'customcertelement_idverify'), [
            self::SHOW_IDCODE => get_string('show:idcode', 'customcertelement_idverify'),
            self::SHOW_LEGALNAME => get_string('show:legalname', 'customcertelement_idverify'),
        ]);
        $mform->addHelpButton('show', 'show', 'customcertelement_idverify');
        $mform->setType('show', PARAM_ALPHA);
        $mform->setDefault('show', self::SHOW_IDCODE);

        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    #[\Override]
    public function normalise_data(stdClass $formdata): array {
        return [
            'show' => (string)($formdata->show ?? self::SHOW_IDCODE),
            'font' => (string)($formdata->font ?? ''),
            'fontsize' => (int)($formdata->fontsize ?? 0),
            'colour' => (string)($formdata->colour ?? ''),
            'width' => (int)($formdata->width ?? 0),
        ];
    }

    #[\Override]
    public function prepare_form(MoodleQuickForm $mform): void {
        $mform->getElement('show')->setValue($this->get_show());
    }

    #[\Override]
    public function validate(array $data): array {
        $show = $data['show'] ?? self::SHOW_IDCODE;
        if (!in_array($show, [self::SHOW_IDCODE, self::SHOW_LEGALNAME], true)) {
            return ['show' => get_string('invalidshow', 'customcertelement_idverify')];
        }
        return [];
    }

    #[\Override]
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        $text = $this->get_text($user, $preview);
        if ($text === '') {
            return;
        }
        if ($renderer) {
            $renderer->render_content($this, $text);
        } else {
            element_helper::render_content($pdf, $this, $text);
        }
    }

    #[\Override]
    public function render_html(?element_renderer $renderer = null): string {
        // Edit screen: always a placeholder, never real data.
        $text = $this->get_placeholder();
        if ($renderer) {
            return (string)$renderer->render_content($this, $text);
        }
        return element_helper::render_html_content($this, $text);
    }

    /**
     * Which value the element prints.
     *
     * @return string SHOW_ constant.
     */
    public function get_show(): string {
        $show = $this->get_payload()['show'] ?? self::SHOW_IDCODE;
        return $show === self::SHOW_LEGALNAME ? self::SHOW_LEGALNAME : self::SHOW_IDCODE;
    }

    /**
     * The text to print for a user.
     *
     * @param stdClass $user Certificate owner.
     * @param bool $preview True for the template preview (placeholder only, nothing decrypted).
     * @return string Empty if the user is not verified or the code cannot be decrypted.
     */
    public function get_text(stdClass $user, bool $preview): string {
        if ($preview) {
            return $this->get_placeholder();
        }

        $identity = identity_manager::get((int)$user->id);
        if (!$identity) {
            debugging(
                'customcertelement_idverify: user ' . (int)$user->id . ' has no verified identity; nothing printed.',
                DEBUG_DEVELOPER
            );
            return '';
        }

        if ($this->get_show() === self::SHOW_LEGALNAME) {
            return $identity->firstname . ' ' . $identity->lastname;
        }

        try {
            $code = identity_manager::decrypt_idcode($identity);
        } catch (\moodle_exception $e) {
            // The site encryption key is missing or has changed (see the local_idverify README on key backups).
            debugging('customcertelement_idverify: cannot decrypt the ID code of user ' . (int)$user->id . ': ' .
                $e->errorcode, DEBUG_DEVELOPER);
            return '';
        }
        if ($code === null) {
            // Manual identity without a code: the birth date takes its place.
            return $identity->birthdate ? date('d.m.Y', strtotime($identity->birthdate . ' 12:00:00')) : '';
        }

        idcode_decrypted::create([
            'context' => element_helper::get_context($this->get_id()),
            'relateduserid' => (int)$user->id,
            'other' => ['elementid' => $this->get_id()],
        ])->trigger();
        return $code;
    }

    /**
     * Placeholder text for previews and the edit screen.
     *
     * @return string
     */
    protected function get_placeholder(): string {
        return get_string('placeholder:' . $this->get_show(), 'customcertelement_idverify');
    }
}
