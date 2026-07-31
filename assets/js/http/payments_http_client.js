/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Payments HTTP client.
 *
 * This module implements the Stripe deposit payment related HTTP requests.
 */
App.Http.Payments = (function () {
    /**
     * Create a Stripe Checkout Session for an appointment deposit and send the
     * payment link to the customer via WhatsApp.
     *
     * @param {Number} appointmentId Appointment ID.
     *
     * @return {*|jQuery}
     */
    function createCheckoutSession(appointmentId) {
        const url = App.Utils.Url.siteUrl('payments/create_checkout_session');

        const data = {
            csrf_token: vars('csrf_token'),
            appointment_id: appointmentId,
        };

        return $.post(url, data);
    }

    return {
        createCheckoutSession,
    };
})();
