<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Phone number normalization helper.
 * ---------------------------------------------------------------------------- */

if (!function_exists('normalize_romanian_phone')) {
    /**
     * Normalize a Romanian mobile phone number to the E.164 format expected by SMSO
     * (country code + number, without the leading '+', e.g. "40722334455").
     *
     * Accepted input examples:
     *   0722334455, +40722334455, 0040722334455, 07-22-33-44-55,
     *   +40 722 334 455, 0040-722-334455
     *
     * @param string|null $phone The raw phone number.
     *
     * @return string|null The normalized number, or null if the number is not a valid RO mobile.
     */
    function normalize_romanian_phone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Strip everything except digits.
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);

        if (empty($digits) || !ctype_digit($digits)) {
            return null;
        }

        // 0040... → 40...
        if (str_starts_with($digits, '0040')) {
            $digits = substr($digits, 2);
        }

        // National format 07xxxxxxxx (10 digits) → convert to 407xxxxxxxx.
        if (strlen($digits) === 10 && str_starts_with($digits, '07')) {
            return '40' . substr($digits, 1);
        }

        // International format 407xxxxxxxx (11 digits).
        if (strlen($digits) === 11 && str_starts_with($digits, '407')) {
            return $digits;
        }

        return null;
    }
}

if (!function_exists('is_valid_romanian_mobile')) {
    /**
     * Check whether a string looks like a valid Romanian mobile number.
     *
     * @param string|null $phone The raw phone number.
     *
     * @return bool
     */
    function is_valid_romanian_mobile(?string $phone): bool
    {
        return normalize_romanian_phone($phone) !== null;
    }
}
