<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.6.0
 * ---------------------------------------------------------------------------- */

if (!function_exists('meta_lead_note_is_new')) {
    /**
     * Decide whether a submitted note deserves a new history entry.
     *
     * The note editor submits the whole textarea on every save, so the text that is already the
     * lead's latest note arrives again whenever someone saves a note without touching it — recording
     * that would pile up identical entries. An empty submission is likewise not a note, and must not
     * be allowed to blank out the note the lead already has.
     *
     * @param string|null $current_note The lead's call_note column, mirrored from its latest note.
     * @param string|null $submitted_note The note text as submitted by the editor.
     *
     * @return bool
     */
    function meta_lead_note_is_new(?string $current_note, ?string $submitted_note): bool
    {
        $submitted_note = trim((string) $submitted_note);

        return $submitted_note !== '' && $submitted_note !== trim((string) $current_note);
    }
}
