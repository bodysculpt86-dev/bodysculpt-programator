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

if (!function_exists('normalize_international_phone')) {
    /**
     * Normalize an international phone number to E.164 digits (country code + number,
     * without the leading '+', e.g. "393123456789").
     *
     * Unlike normalize_romanian_phone(), this accepts numbers from any country, which is
     * what the WhatsApp (Flaxxa/Meta) sender needs. Local Romanian mobile numbers without
     * a country code (07xxxxxxxx) still default to Romania for backwards compatibility.
     *
     * Accepted input examples:
     *   0722334455, +40722334455, 0040722334455,
     *   +393123456789, 00393123456789, +1 202 555 0134
     *
     * @param string|null $phone The raw phone number.
     *
     * @return string|null The normalized E.164 digits, or null if the number is invalid.
     */
    function normalize_international_phone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Strip everything except digits.
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '' || !ctype_digit($digits)) {
            return null;
        }

        // "00" international prefix → strip it (0040... → 40..., 0039... → 39...).
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Local Romanian mobile format 07xxxxxxxx (10 digits) → convert to 407xxxxxxxx.
        if (strlen($digits) === 10 && str_starts_with($digits, '07')) {
            return '40' . substr($digits, 1);
        }

        // A leading "0" here means a national trunk prefix with no country code
        // (e.g. a Romanian landline 0212345678). That is ambiguous → reject.
        if (str_starts_with($digits, '0')) {
            return null;
        }

        // E.164 allows at most 15 digits; anything below 8 digits is not a plausible
        // international phone number.
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
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
