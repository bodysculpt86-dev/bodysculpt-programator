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
 * Invoices page client-side logic.
 *
 * Phase A shell: opens the "Generează factură fiscală" modal placeholder.
 * Later phases add: billing clients + ANAF lookup (B), line items from
 * services/packages + totals (C), SmartBill emission + history (D).
 */
App.Pages.Invoices = (function () {
    const $page = $('#invoices-page');
    const $issueInvoiceModal = $('#issue-invoice-modal');

    let issueModalInstance = null;

    /**
     * Add the page event listeners.
     */
    function addEventListeners() {
        /**
         * Event: "Emite factură" button "Click"
         */
        $page.on('click', '#issue-invoice', () => {
            if (!issueModalInstance) {
                issueModalInstance = new bootstrap.Modal($issueInvoiceModal[0]);
            }

            issueModalInstance.show();
        });
    }

    /**
     * Initialize the page module.
     */
    function initialize() {
        addEventListeners();
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        initialize,
    };
})();
