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

if (!function_exists('appointment_creator_label')) {
    /**
     * Describe who created an appointment.
     *
     * Appointments created before creator tracking existed have neither a user nor a
     * source recorded. Those rows must not be guessed at, so they render as an em dash.
     *
     * @param string|null $creator_name Name of the logged-in user that created the appointment, when known.
     * @param string|null $created_via  Source recorded at insert time: 'admin', 'online', 'meta_leads',
     *                                  'api', or null for rows that predate tracking.
     * @param array $labels Translated labels keyed by 'meta_leads', 'online' and 'api'.
     *
     * @return string The label to display, or an em dash when the creator is unknown.
     */
    function appointment_creator_label(?string $creator_name, ?string $created_via, array $labels): string
    {
        if (!empty($creator_name)) {
            return $created_via === 'meta_leads'
                ? $creator_name . ' (' . ($labels['meta_leads'] ?? '') . ')'
                : $creator_name;
        }

        return match ($created_via) {
            'online' => $labels['online'] ?? '—',
            'api' => $labels['api'] ?? '—',
            default => '—',
        };
    }
}

if (!function_exists('appointment_creator_suffix')) {
    /**
     * Build the part that follows the creation date in the "created at" line.
     *
     * A named user is introduced with a preposition ("de Ana"), while the unattributed sources are
     * separated instead ("· Online"), because prepositioning them would read as if they were people.
     *
     * @param string|null $creator_name Name of the logged-in user that created the appointment, when known.
     * @param string|null $created_via  Source recorded at insert time.
     * @param array $labels Translated labels keyed by 'meta_leads', 'online', 'api' and 'by'.
     *
     * @return string The suffix, including its leading separator.
     */
    function appointment_creator_suffix(?string $creator_name, ?string $created_via, array $labels): string
    {
        $label = appointment_creator_label($creator_name, $created_via, $labels);

        return !empty($creator_name) ? ' ' . ($labels['by'] ?? '') . ' ' . $label : ' · ' . $label;
    }
}
