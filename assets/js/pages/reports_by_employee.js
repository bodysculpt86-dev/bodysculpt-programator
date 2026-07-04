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
 * Per-employee revenue report page.
 *
 * Fetches daily revenue for a selected employee and date range.
 */
App.Pages.ReportsByEmployee = (function () {
    const $page = $('#reports-by-employee-page');
    const $dateRangeSelector = $page.find('.date-range-selector');
    const $employeeSelect = $('#employee-select');
    const $emptyHint = $('#reports-empty-hint');
    const $emptyHintText = $('#reports-empty-hint-text');
    const $dailySection = $('#daily-revenue-section');
    const $dailyTitle = $('#daily-revenue-title');
    const $dailyBody = $('#daily-revenue-body');
    const $dailyFoot = $('#daily-revenue-foot');
    const $dailyTotal = $('#daily-revenue-total');

    const moment = window.moment;
    const currency = vars('currency') || '';
    const isAdmin = vars('is_admin') === true || vars('is_admin') === 'true' || vars('is_admin') === 1;

    let currentStartDate = '';
    let currentEndDate = '';
    let currentLabel = '';

    /**
     * Initialize the page.
     */
    function init() {
        App.Utils.DateRangeSelector.init($dateRangeSelector, (startDate, endDate, label) => {
            currentStartDate = startDate;
            currentEndDate = endDate;
            currentLabel = label;
            loadReport();
        });

        if ($employeeSelect.length) {
            $employeeSelect.on('change', () => {
                loadReport();
            });
        }
    }

    /**
     * Fetch revenue data for the current employee and date range.
     */
    function loadReport() {
        if (isAdmin && (!$employeeSelect.length || !$employeeSelect.val())) {
            showHint('select_employee_first');
            return;
        }

        if (!currentStartDate || !currentEndDate) {
            showHint('select_range_first');
            return;
        }

        const url = App.Utils.Url.siteUrl('reports/get_employee_revenue');
        const requestData = {
            csrf_token: vars('csrf_token'),
            start_date: currentStartDate,
            end_date: currentEndDate,
        };

        if (isAdmin) {
            requestData.employee_id = $employeeSelect.val();
        }

        $.post(url, requestData)
            .done((response) => {
                $emptyHint.addClass('d-none');
                $dailySection.removeClass('d-none');

                $dailyTitle.text(currentLabel || lang('daily'));

                renderDaily(response.daily || []);
            })
            .fail(() => {
                App.Utils.Message.show('An error occurred while fetching the report.');
            });
    }

    /**
     * Show the placeholder hint and hide the table.
     *
     * @param {string} translationKey
     */
    function showHint(translationKey) {
        $emptyHintText.text(lang(translationKey));
        $emptyHint.removeClass('d-none');
        $dailySection.addClass('d-none');
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
            total += Number(row.total);

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
