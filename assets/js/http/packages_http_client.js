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

/**
 * Packages HTTP client.
 *
 * This module implements the packages related HTTP requests.
 */
App.Http.Packages = (function () {
    /**
     * Save (create or update) a package.
     *
     * @param {Object} packageData
     *
     * @return {Object}
     */
    function save(packageData) {
        return packageData.id ? update(packageData) : store(packageData);
    }

    /**
     * Create a package.
     *
     * @param {Object} packageData
     *
     * @return {Object}
     */
    function store(packageData) {
        const url = App.Utils.Url.siteUrl('packages/store');

        const data = {
            csrf_token: vars('csrf_token'),
            package: packageData,
        };

        return $.post(url, data);
    }

    /**
     * Update a package.
     *
     * @param {Object} packageData
     *
     * @return {Object}
     */
    function update(packageData) {
        const url = App.Utils.Url.siteUrl('packages/update');

        const data = {
            csrf_token: vars('csrf_token'),
            package: packageData,
        };

        return $.post(url, data);
    }

    /**
     * Delete a package.
     *
     * @param {Number} packageId
     *
     * @return {Object}
     */
    function destroy(packageId) {
        const url = App.Utils.Url.siteUrl('packages/destroy');

        const data = {
            csrf_token: vars('csrf_token'),
            package_id: packageId,
        };

        return $.post(url, data);
    }

    /**
     * Search packages by keyword and filters.
     *
     * @param {String} keyword
     * @param {Number|null} categoryId
     * @param {Number|null} isActive
     * @param {Number} [limit]
     * @param {Number} [offset]
     * @param {String} [orderBy]
     *
     * @return {Object}
     */
    function search(keyword, categoryId = null, isActive = null, limit = null, offset = null, orderBy = null) {
        const url = App.Utils.Url.siteUrl('packages/search');

        const data = {
            csrf_token: vars('csrf_token'),
            keyword,
            category_id: categoryId,
            is_active: isActive,
            limit,
            offset,
            order_by: orderBy || undefined,
        };

        return $.post(url, data);
    }

    /**
     * Find a package.
     *
     * @param {Number} packageId
     *
     * @return {Object}
     */
    function find(packageId) {
        const url = App.Utils.Url.siteUrl('packages/find');

        const data = {
            csrf_token: vars('csrf_token'),
            package_id: packageId,
        };

        return $.post(url, data);
    }

    return {
        save,
        store,
        update,
        destroy,
        search,
        find,
    };
})();
