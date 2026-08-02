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

if (!function_exists('generate_payment_slug')) {
    /**
     * Generate a cryptographically secure short slug for payment links.
     *
     * Uses random_bytes() (CSPRNG) mapped onto a case-sensitive alphanumeric
     * (base62) alphabet. 8 characters give 62^8 (~2.2e14) combinations, which
     * makes brute-force enumeration impractical.
     *
     * @param int $length Slug length (6-16).
     *
     * @return string
     *
     * @throws Exception If random_bytes() cannot gather entropy.
     */
    function generate_payment_slug(int $length = 8): string
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $max = strlen($alphabet) - 1;

        $bytes = random_bytes($length);

        $slug = '';

        for ($i = 0; $i < $length; $i++) {
            $slug .= $alphabet[ord($bytes[$i]) % ($max + 1)];
        }

        return $slug;
    }
}
