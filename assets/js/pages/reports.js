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
 * Revenue reports page.
 *
 * Fetches daily and monthly revenue for a selected date range.
 */
App.Pages.Reports = (function () {
    const $startDate = $('#start-date');
    const $endDate = $('#end-date');
    const $form = $('#revenue-filter-form');
    const $dailyBody = $('#daily-revenue-body');
    const $dailyFoot = $('#daily-revenue-foot');
    const $dailyTotal = $('#daily-revenue-total');
    const $monthlyBody = $('#monthly-revenue-body');
    const $monthlyFoot = $('#monthly-revenue-foot');
    const $monthlyTotal = $('#monthly-revenue-total');

    const moment = window.moment;
    const currency = vars('currency') || '';

    /**
     * Initialize the page.
     */
    function init() {
        App.Utils.UI.initializeDatePicker($startDate, { dateFormat: 'Y-m-d' });
        App.Utils.UI.initializeDatePicker($endDate, { dateFormat: 'Y-m-d' });

        const today = moment();

        $startDate[0]._flatpickr.setDate(today.clone().startOf('month').format('YYYY-MM-DD'));
        $endDate[0]._flatpickr.setDate(today.format('YYYY-MM-DD'));

        addEventListeners();

        loadReport();
    }

    /**
     * Add the page event listeners.
     */
    function addEventListeners() {
        $form.on('submit', (event) => {
            event.preventDefault();
            loadReport();
        });
    }

    /**
     * Fetch revenue data for the current date range.
     */
    function loadReport() {
        const startDate = $startDate.val();
        const endDate = $endDate.val();

        if (!startDate || !endDate) {
            return;
        }

        const url = App.Utils.Url.siteUrl('reports/get_revenue');

        $.post(url, {
            csrf_token: vars('csrf_token'),
            start_date: startDate,
            end_date: endDate,
        })
            .done((response) => {
                renderDaily(response.daily || []);
                renderMonthly(response.monthly || []);
            })
            .fail(() => {
                App.Utils.Message.show('An error occurred while fetching the report.');
            });
    }

    /**
     * Render the daily revenue table.
     *
     * @param {Array} rows
     */
    function renderDaily(rows) {
        $dailyBody.empty();

        if (!rows.length) {
            $dailyBody.append(`
                <tr>
                    <td colspan="2" class="text-muted text-center">
                        ${lang('no_records_found')}
                    </td>
                </tr>
            `);
            $dailyFoot.addClass('d-none');
            return;
        }

        let total = 0;

        rows.forEach((row) => {
            total += row.total;

            $dailyBody.append(`
                <tr>
                    <td>${formatDate(row.date)}</td>
                    <td class="text-end">${formatAmount(row.total)}</td>
                </tr>
            `);
        });

        $dailyTotal.text(formatAmount(total));
        $dailyFoot.removeClass('d-none');
    }

    /**
     * Render the monthly revenue table.
     *
     * @param {Array} rows
     */
    function renderMonthly(rows) {
        $monthlyBody.empty();

        if (!rows.length) {
            $monthlyBody.append(`
                <tr>
                    <td colspan="2" class="text-muted text-center">
                        ${lang('no_records_found')}
                    </td>
                </tr>
            `);
            $monthlyFoot.addClass('d-none');
            return;
        }

        let total = 0;

        rows.forEach((row) => {
            total += row.total;

            $monthlyBody.append(`
                <tr>
                    <td>${formatMonth(row.month)}</td>
                    <td class="text-end">${formatAmount(row.total)}</td>
                </tr>
            `);
        });

        $monthlyTotal.text(formatAmount(total));
        $monthlyFoot.removeClass('d-none');
    }

    /**
     * Format a date value for display.
     *
     * @param {string} date
     *
     * @return {string}
     */
    function formatDate(date) {
        const value = moment(date);
        return value.isValid() ? value.format('LL') : date;
    }

    /**
     * Format a month value for display.
     *
     * @param {string} month
     *
     * @return {string}
     */
    function formatMonth(month) {
        const value = moment(month, 'YYYY-MM');
        return value.isValid() ? value.format('MMMM YYYY') : month;
    }

    /**
     * Format an amount value for display.
     *
     * @param {number} amount
     *
     * @return {string}
     */
    function formatAmount(amount) {
        const suffix = currency ? ' ' + currency : '';
        return Number(amount).toFixed(2) + suffix;
    }

    document.addEventListener('DOMContentLoaded', init);

    return {
        init,
    };
})();
