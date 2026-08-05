/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Location autocomplete utility.
 *
 * Attaches native <datalist> suggestions (Romanian judete / municipii, see
 * assets/js/data/ro_locations.js) to the free-text county and city inputs.
 *
 * The inputs stay plain text fields: suggestions only help typing, any value
 * that is not part of the list is still allowed and saved as-is. Changing the
 * county only repopulates the city suggestions, it never alters the typed
 * city value.
 */
window.App.Utils.LocationAutocomplete = (function () {
    /**
     * Normalize a value for loose matching (case + diacritics insensitive).
     *
     * @param {String} value
     *
     * @return {String}
     */
    function normalize(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    /**
     * Get the dataset (graceful fallback when the data asset is not loaded).
     *
     * @return {Object}
     */
    function dataset() {
        return (window.App.Data && window.App.Data.RoLocations) || {counties: [], municipalities: {}};
    }

    /**
     * Get a flat, sorted list with all the municipalities (incl. the sectors of București).
     *
     * @return {String[]}
     */
    function allMunicipalities() {
        const all = [];

        Object.values(dataset().municipalities).forEach((municipalities) => all.push(...municipalities));

        return [...new Set(all)].sort((a, b) => a.localeCompare(b, 'ro'));
    }

    /**
     * Find the municipalities of a county name (free-typed, loosely matched).
     *
     * Returns null when the county is empty or unknown - callers fall back to
     * suggesting all municipalities in that case.
     *
     * @param {String} county
     *
     * @return {String[]|null}
     */
    function municipalitiesOf(county) {
        const normalized = normalize(county);

        if (!normalized) {
            return null;
        }

        let match = dataset().counties.find((candidate) => normalize(candidate) === normalized);

        // Common variants, e.g. "Municipiul Bucuresti" as returned by the ANAF CUI lookup.
        if (!match && normalized.includes('bucuresti')) {
            match = 'București';
        }

        return match ? dataset().municipalities[match] || null : null;
    }

    /**
     * Create (or reuse) the <datalist> element bound to an input.
     *
     * @param {jQuery} $input
     * @param {String} suffix Fallback id part when the input has no id.
     *
     * @return {HTMLElement|null}
     */
    function ensureDatalist($input, suffix) {
        const input = $input.get(0);

        if (!input) {
            return null;
        }

        const listId = 'ro-locations-' + (input.id || suffix);

        let datalist = document.getElementById(listId);

        if (!datalist) {
            datalist = document.createElement('datalist');
            datalist.id = listId;
            input.after(datalist);
        }

        input.setAttribute('list', listId);
        input.setAttribute('autocomplete', 'off');

        return datalist;
    }

    /**
     * Replace the options of a <datalist> (the input value is never touched).
     *
     * @param {HTMLElement} datalist
     * @param {String[]} values
     */
    function populate(datalist, values) {
        datalist.innerHTML = '';

        values.forEach((value) => {
            const option = document.createElement('option');

            option.value = value;

            datalist.appendChild(option);
        });
    }

    /**
     * Attach the 42 județe (incl. București) as suggestions on a county input.
     *
     * @param {jQuery} $county County (județ) text input.
     */
    function attachCounty($county) {
        if (!$county || !$county.length || $county.data('ro-locations-attached')) {
            return;
        }

        $county.data('ro-locations-attached', true);

        populate(ensureDatalist($county, 'county'), dataset().counties);
    }

    /**
     * Attach municipality suggestions on a city input.
     *
     * When a county input is provided, the suggestions cascade: a (loosely)
     * matching county filters the municipalities of that county; an empty or
     * unknown county falls back to suggesting all municipalities. The typed
     * city value is never modified.
     *
     * @param {jQuery} $city City (oraș) text input.
     * @param {jQuery} [$county] Optional county (județ) text input for cascading.
     */
    function attachCity($city, $county) {
        if (!$city || !$city.length || $city.data('ro-locations-attached')) {
            return;
        }

        $city.data('ro-locations-attached', true);

        const datalist = ensureDatalist($city, 'city');

        function refresh() {
            const municipalities = $county && $county.length ? municipalitiesOf($county.val()) : null;

            populate(datalist, municipalities || allMunicipalities());
        }

        refresh();

        if ($county && $county.length) {
            attachCounty($county);

            $county.on('input change', refresh);
        }
    }

    return {
        attachCounty,
        attachCity,
    };
})();
