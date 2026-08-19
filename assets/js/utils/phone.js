/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Phone number utility (country prefix dropdown + normalization).
 *
 * This module keeps the phone input split into a country prefix select (flag + dial code) and a local
 * number input, and composes/parses the normalized E.164-like value (e.g. "+40712345678") that is
 * stored in the "phone_number" column. The same normalization rules are applied server-side in
 * Customers_model::normalize_phone_number() (single source of truth).
 */
window.App.Utils.Phone = (function () {
    /**
     * Common countries shown in the prefix dropdown (Romania first = default).
     *
     * @type {Array<{iso: String, flag: String, dial: String, name: String}>}
     */
    const COUNTRIES = [
        { iso: 'RO', flag: '🇷🇴', dial: '40', name: 'România' },
        { iso: 'MD', flag: '🇲🇩', dial: '373', name: 'Moldova' },
        { iso: 'HU', flag: '🇭🇺', dial: '36', name: 'Ungaria' },
        { iso: 'BG', flag: '🇧🇬', dial: '359', name: 'Bulgaria' },
        { iso: 'GR', flag: '🇬🇷', dial: '30', name: 'Grecia' },
        { iso: 'IT', flag: '🇮🇹', dial: '39', name: 'Italia' },
        { iso: 'DE', flag: '🇩🇪', dial: '49', name: 'Germania' },
        { iso: 'AT', flag: '🇦🇹', dial: '43', name: 'Austria' },
        { iso: 'CH', flag: '🇨🇭', dial: '41', name: 'Elveția' },
        { iso: 'FR', flag: '🇫🇷', dial: '33', name: 'Franța' },
        { iso: 'BE', flag: '🇧🇪', dial: '32', name: 'Belgia' },
        { iso: 'NL', flag: '🇳🇱', dial: '31', name: 'Olanda' },
        { iso: 'ES', flag: '🇪🇸', dial: '34', name: 'Spania' },
        { iso: 'PT', flag: '🇵🇹', dial: '351', name: 'Portugalia' },
        { iso: 'GB', flag: '🇬🇧', dial: '44', name: 'Marea Britanie' },
        { iso: 'IE', flag: '🇮🇪', dial: '353', name: 'Irlanda' },
        { iso: 'PL', flag: '🇵🇱', dial: '48', name: 'Polonia' },
        { iso: 'CZ', flag: '🇨🇿', dial: '420', name: 'Cehia' },
        { iso: 'SK', flag: '🇸🇰', dial: '421', name: 'Slovacia' },
        { iso: 'UA', flag: '🇺🇦', dial: '380', name: 'Ucraina' },
        { iso: 'TR', flag: '🇹🇷', dial: '90', name: 'Turcia' },
        { iso: 'US', flag: '🇺🇸', dial: '1', name: 'SUA / Canada' },
    ];

    const DEFAULT_PREFIX = '+40';

    // Longest dial codes first, so that e.g. "+359" wins over a hypothetical shorter match.
    const DIAL_CODES_BY_LENGTH = COUNTRIES.map((country) => country.dial).sort((a, b) => b.length - a.length);

    /**
     * Clean a raw phone value: remove separators, convert "00" to "+".
     *
     * @param {String} value
     *
     * @return {Object} Returns an object with "hasPlus" and "digits" properties.
     */
    function clean(value) {
        let number = String(value || '').trim();

        let hasPlus = number.startsWith('+');

        let digits = number.replace(/\D/g, '');

        if (digits.startsWith('00')) {
            digits = digits.substring(2);
            hasPlus = true;
        }

        return { hasPlus, digits };
    }

    /**
     * Normalize any phone value into "+<digits>" form (used for values that already carry a prefix).
     *
     * @param {String} value
     *
     * @return {String}
     */
    function normalize(value) {
        const { hasPlus, digits } = clean(value);

        if (!digits) {
            return '';
        }

        if (hasPlus) {
            return '+' + digits;
        }

        // Digits only: detect a known country dial code on long numbers (e.g. "40712345678").
        if (digits.length >= 10) {
            const dial = DIAL_CODES_BY_LENGTH.find((dial) => digits.startsWith(dial));

            if (dial) {
                return '+' + digits;
            }
        }

        // Legacy local format (e.g. "0712345678" or "712345678"): treat as Romanian by default.
        return DEFAULT_PREFIX + digits.replace(/^0+/, '');
    }

    /**
     * Parse a stored phone value into prefix + local parts for the form.
     *
     * @param {String} value Stored phone number (any format, e.g. "+40712345678", "0712345678").
     *
     * @return {Object} Returns an object with "prefix" (e.g. "+40") and "local" (e.g. "712345678").
     */
    function parse(value) {
        const result = { prefix: DEFAULT_PREFIX, local: '' };

        if (!value) {
            return result;
        }

        const normalized = normalize(value);

        if (!normalized) {
            return result;
        }

        const digits = normalized.substring(1); // strip the leading "+"

        const dial = DIAL_CODES_BY_LENGTH.find((dial) => digits.startsWith(dial));

        if (dial) {
            result.prefix = '+' + dial;
            result.local = digits.substring(dial.length).replace(/^0+/, '');
        } else {
            // Unknown international prefix: keep the full normalized value in the local input so that
            // compose() stores it back unchanged instead of forcing the Romanian prefix on it.
            result.local = normalized;
        }

        return result;
    }

    /**
     * Compose the normalized full phone number from the prefix select and the local input.
     *
     * @param {String} prefix Selected prefix (e.g. "+40").
     * @param {String} local Local number as typed (e.g. "712345678" or "0712 345 678").
     *
     * @return {String} Normalized phone number (e.g. "+40712345678") or an empty string.
     */
    function compose(prefix, local) {
        const number = String(local || '').trim();

        if (!number) {
            return '';
        }

        // The local input already carries an international prefix (unknown country case): keep it.
        if (number.startsWith('+') || number.replace(/\D/g, '').startsWith('00')) {
            return normalize(number);
        }

        const digits = number.replace(/\D/g, '').replace(/^0+/, '');

        if (!digits) {
            return '';
        }

        return (prefix || DEFAULT_PREFIX) + digits;
    }

    /**
     * Fill a prefix select element with the country options.
     *
     * @param {jQuery} $select
     */
    function populate($select) {
        if (!$select.length || $select.data('phone-prefix-populated')) {
            return;
        }

        COUNTRIES.forEach((country) => {
            $('<option/>', {
                value: '+' + country.dial,
                text: `${country.flag} +${country.dial}`,
                title: country.name,
            }).appendTo($select);
        });

        $select.val(DEFAULT_PREFIX);

        $select.data('phone-prefix-populated', true);
    }

    /**
     * Set the prefix select and local input from a stored phone value.
     *
     * @param {jQuery} $select Prefix select element.
     * @param {jQuery} $input Local number input element.
     * @param {String} value Stored phone number (any format).
     */
    function applyTo($select, $input, value) {
        const parts = parse(value);

        populate($select);

        $select.val(parts.prefix);

        $input.val(parts.local);
    }

    // Auto-populate any prefix select present on the page.
    $(() => {
        $('.phone-prefix-select').each((index, select) => populate($(select)));
    });

    return {
        COUNTRIES,
        DEFAULT_PREFIX,
        normalize,
        parse,
        compose,
        populate,
        applyTo,
    };
})();
