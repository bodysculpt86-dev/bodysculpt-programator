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
 * Invoices HTTP client.
 *
 * This module implements the invoicing related HTTP requests.
 */
App.Http.Invoices = (function () {
    /**
     * Search billing clients by name or CUI.
     *
     * @param {String} keyword
     *
     * @return {Object}
     */
    function searchClients(keyword) {
        const url = App.Utils.Url.siteUrl('invoices/search_clients');

        const data = {
            csrf_token: vars('csrf_token'),
            keyword,
        };

        return $.post(url, data);
    }

    /**
     * Look up a company by CUI in the ANAF registry.
     *
     * @param {String} cui
     *
     * @return {Object}
     */
    function lookupCui(cui) {
        const url = App.Utils.Url.siteUrl('invoices/lookup_cui');

        const data = {
            csrf_token: vars('csrf_token'),
            cui,
        };

        return $.post(url, data);
    }

    /**
     * Save (create or update) a billing client.
     *
     * @param {Object} clientData
     *
     * @return {Object}
     */
    function saveClient(clientData) {
        const url = App.Utils.Url.siteUrl('invoices/save_client');

        const data = {
            csrf_token: vars('csrf_token'),
            client: clientData,
        };

        return $.post(url, data);
    }

    /**
     * List the services catalog for invoice lines.
     *
     * @return {Object}
     */
    function listServices() {
        const url = App.Utils.Url.siteUrl('invoices/list_services');

        const data = {
            csrf_token: vars('csrf_token'),
        };

        return $.post(url, data);
    }

    /**
     * List the packages (abonamente) catalog for invoice lines.
     *
     * @return {Object}
     */
    function listPackages() {
        const url = App.Utils.Url.siteUrl('invoices/list_packages');

        const data = {
            csrf_token: vars('csrf_token'),
        };

        return $.post(url, data);
    }

    /**
     * Issue an invoice via SmartBill.
     *
     * @param {Object} payload billing_client_id, issue_date, payment_method,
     *                         is_draft, idempotency_key, lines[]
     *
     * @return {Object}
     */
    function issue(payload) {
        const url = App.Utils.Url.siteUrl('invoices/issue');

        const data = {
            csrf_token: vars('csrf_token'),
            ...payload,
        };

        return $.post(url, data);
    }

    /**
     * List issued invoices for the history table.
     *
     * @return {Object}
     */
    function history() {
        const url = App.Utils.Url.siteUrl('invoices/history');

        const data = {
            csrf_token: vars('csrf_token'),
        };

        return $.post(url, data);
    }

    return {
        searchClients,
        lookupCui,
        saveClient,
        listServices,
        listPackages,
        issue,
        history,
    };
})();
