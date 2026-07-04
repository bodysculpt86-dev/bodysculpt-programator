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
 * Activity matrix report page.
 *
 * Fetches a month × provider matrix of appointment counts and revenue.
 */
App.Pages.ReportsActivity = (function () {
    const $emptyHint = $('#reports-empty-hint');
    const $matrixSection = $('#activity-matrix-section');
    const $matrixBody = $('#activity-matrix-body');
    const $matrixFoot = $('#activity-matrix-foot');

    const moment = window.moment;
    const currency = vars('currency') || '';

    /**
     * Initialize the page.
     */
    function init() {
        App.Utils.DateRangeSelector.init($('#reports-activity-page .date-range-selector'), (startDate, endDate) => {
            loadMatrix(startDate, endDate);
        });
    }

    /**
     * Fetch and render the activity matrix.
     *
     * @param {string} startDate
     * @param {string} endDate
     */
    function loadMatrix(startDate, endDate) {
        if (!startDate || !endDate) {
            return;
        }

        const url = App.Utils.Url.siteUrl('reports/get_activity');

        $.post(url, {
            csrf_token: vars('csrf_token'),
            start_date: startDate,
            end_date: endDate,
        })
            .done((response) => {
                $emptyHint.addClass('d-none');
                $matrixSection.removeClass('d-none');

                renderMatrix(response.periods || [], response.provider_totals || {}, response.grand_total || { cnt: 0, total: 0.0 });
            })
            .fail(() => {
                App.Utils.Message.show('An error occurred while fetching the report.');
            });
    }

    /**
     * Render the matrix body and footer.
     *
     * @param {Array} periods
     * @param {Object} providerTotals
     * @param {Object} grandTotal
     */
    function renderMatrix(periods, providerTotals, grandTotal) {
        const columns = $('#activity-head tr:last-child th[data-provider-id]')
            .toArray()
            .map((th) => ({
                providerId: $(th).data('provider-id').toString(),
                type: $(th).data('type').toString(),
            }));

        $matrixBody.empty();

        if (!periods.length) {
            $matrixBody.append(`
                <tr>
                    <td colspan="${columns.length + 1}" class="text-muted text-center">
                        ${lang('no_records_found')}
                    </td>
                </tr>
            `);
        } else {
            periods.forEach((period) => {
                const row = $('<tr></tr>');

                row.append(`<td class="fw-light">${formatPeriod(period.period)}</td>`);

                columns.forEach((column) => {
                    let value;

                    if (column.providerId === 'total') {
                        value = column.type === 'count' ? period.row_count : period.row_total;
                    } else if (period.cells[column.providerId]) {
                        value = column.type === 'count'
                            ? period.cells[column.providerId].cnt
                            : period.cells[column.providerId].total;
                    } else {
                        value = null;
                    }

                    row.append(renderCell(value, column.type));
                });

                $matrixBody.append(row);
            });
        }

        renderFooter(columns, providerTotals, grandTotal);
    }

    /**
     * Render the footer totals row.
     *
     * @param {Array} columns
     * @param {Object} providerTotals
     * @param {Object} grandTotal
     */
    function renderFooter(columns, providerTotals, grandTotal) {
        const $row = $matrixFoot.find('tr');
        $row.empty();

        $row.append(`<td>${lang('total')}</td>`);

        columns.forEach((column) => {
            const value = column.providerId === 'total'
                ? (column.type === 'count' ? grandTotal.cnt : grandTotal.total)
                : (providerTotals[column.providerId]
                    ? (column.type === 'count' ? providerTotals[column.providerId].cnt : providerTotals[column.providerId].total)
                    : null);

            $row.append(renderCell(value, column.type));
        });
    }

    /**
     * Render a single matrix cell.
     *
     * @param {number|null} value
     * @param {string} type 'count' | 'total'
     *
     * @return {string}
     */
    function renderCell(value, type) {
        if (value === null || value === undefined || value === 0) {
            return `<td class="text-center text-muted">-</td>`;
        }

        if (type === 'count') {
            return `<td class="text-center">${Number(value)}</td>`;
        }

        return `<td class="text-end">${formatAmount(value)}</td>`;
    }

    /**
     * Format a YYYY-MM period for display.
     *
     * @param {string} period
     *
     * @return {string}
     */
    function formatPeriod(period) {
        const value = moment(period, 'YYYY-MM');
        return value.isValid() ? value.format('MMMM YYYY') : period;
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
