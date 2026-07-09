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
 * Customer packages HTTP client.
 *
 * This module implements the customer packages related HTTP requests.
 */
App.Http.CustomerPackages = (function () {
    /**
     * Sell a package to a customer.
     *
     * @param {Number} customerId
     * @param {Number} packageId
     * @param {String|null} notes
     *
     * @return {Object}
     */
    function store(customerId, packageId, notes = null) {
        const url = App.Utils.Url.siteUrl('customer_packages/store');

        const data = {
            csrf_token: vars('csrf_token'),
            customer_id: customerId,
            package_id: packageId,
            notes: notes,
        };

        return $.post(url, data);
    }

    /**
     * Delete a customer package.
     *
     * @param {Number} customerPackageId
     *
     * @return {Object}
     */
    function destroy(customerPackageId) {
        const url = App.Utils.Url.siteUrl('customer_packages/destroy');

        const data = {
            csrf_token: vars('csrf_token'),
            customer_package_id: customerPackageId,
        };

        return $.post(url, data);
    }

    /**
     * Search sold customer packages.
     *
     * @param {String} keyword
     * @param {Number|null} isActive
     * @param {Number} [limit]
     * @param {Number} [offset]
     * @param {String} [orderBy]
     *
     * @return {Object}
     */
    function search(keyword, isActive = null, limit = null, offset = null, orderBy = null) {
        const url = App.Utils.Url.siteUrl('customer_packages/search');

        const data = {
            csrf_token: vars('csrf_token'),
            keyword,
            is_active: isActive,
            limit,
            offset,
            order_by: orderBy || undefined,
        };

        return $.post(url, data);
    }

    /**
     * Search sold customer packages by customer ID.
     *
     * @param {Number} customerId
     * @param {Number|null} isActive
     *
     * @return {Object}
     */
    function searchByCustomer(customerId, isActive = 1) {
        const url = App.Utils.Url.siteUrl('customer_packages/search_by_customer');

        const data = {
            csrf_token: vars('csrf_token'),
            customer_id: customerId,
            is_active: isActive,
        };

        return $.post(url, data);
    }

    /**
     * Find a customer package.
     *
     * @param {Number} customerPackageId
     *
     * @return {Object}
     */
    function find(customerPackageId) {
        const url = App.Utils.Url.siteUrl('customer_packages/find');

        const data = {
            csrf_token: vars('csrf_token'),
            customer_package_id: customerPackageId,
        };

        return $.post(url, data);
    }

    /**
     * Update a customer package (manual item adjustments).
     *
     * @param {Number} customerPackageId
     * @param {Array} items
     *
     * @return {Object}
     */
    function update(customerPackageId, items) {
        const url = App.Utils.Url.siteUrl('customer_packages/update');

        const data = {
            csrf_token: vars('csrf_token'),
            customer_package_id: customerPackageId,
            items: items,
        };

        return $.post(url, data);
    }

    return {
        store,
        destroy,
        search,
        searchByCustomer,
        find,
        update,
    };
})();
