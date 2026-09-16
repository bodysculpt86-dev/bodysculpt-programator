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
 * Meta Leads HTTP client.
 *
 * This module implements the meta leads related HTTP requests.
 */
App.Http.MetaLeads = (function () {
    /**
     * Search meta leads by keyword and optional status.
     *
     * @param {String} keyword
     * @param {String|null} [status]
     * @param {Number} [limit]
     * @param {Number} [offset]
     *
     * @return {Object}
     */
    function search(keyword = '', status = null, limit = 20, offset = 0) {
        const url = App.Utils.Url.siteUrl('meta_leads/search');

        const data = {
            csrf_token: vars('csrf_token'),
            keyword,
            status: status || undefined,
            limit,
            offset,
        };

        return $.post(url, data);
    }

    /**
     * Fetch a single lead including its parsed raw form fields.
     *
     * @param {Number} leadId
     *
     * @return {Object}
     */
    function show(leadId) {
        const url = App.Utils.Url.siteUrl('meta_leads/show');

        const data = {
            csrf_token: vars('csrf_token'),
            lead_id: leadId,
        };

        return $.post(url, data);
    }

    /**
     * Delete a meta lead.
     *
     * @param {Number} leadId
     *
     * @return {Object}
     */
    function destroy(leadId) {
        const url = App.Utils.Url.siteUrl('meta_leads/destroy');

        const data = {
            csrf_token: vars('csrf_token'),
            lead_id: leadId,
        };

        return $.post(url, data);
    }

    /**
     * Search meta leads for the internal call workflow, filtered by call_status.
     *
     * @param {String|null} callStatus
     * @param {String} keyword
     * @param {Number} limit
     * @param {Number} offset
     *
     * @return {Object}
     */
    function searchCalls(callStatus = null, keyword = '', limit = 200, offset = 0) {
        const url = App.Utils.Url.siteUrl('meta_leads/search_calls');

        const data = {
            csrf_token: vars('csrf_token'),
            call_status: callStatus || undefined,
            keyword,
            limit,
            offset,
        };

        return $.post(url, data);
    }

    /**
     * Update a lead's call workflow fields (call_status / call_note).
     *
     * @param {Number} leadId
     * @param {Object} data
     *
     * @return {Object}
     */
    function updateCall(leadId, data) {
        const url = App.Utils.Url.siteUrl('meta_leads/update_call');

        const postData = Object.assign(
            {
                csrf_token: vars('csrf_token'),
                lead_id: leadId,
            },
            data,
        );

        return $.post(url, postData);
    }

    return {
        search,
        searchCalls,
        show,
        updateCall,
        destroy,
    };
})();
