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
 * Fetches a month/category/service × provider matrix of appointment counts and revenue,
 * filtered by payment method.
 */
App.Pages.ReportsActivity = (function () {
    const $emptyHint = $('#reports-empty-hint');
    const $matrixSection = $('#activity-matrix-section');
    const $matrixBody = $('#activity-matrix-body');
    const $matrixFoot = $('#activity-matrix-foot');
    const $matrixRowHeader = $('#matrix-row-header');
    const $paymentFilters = $('.payment-filter');
    const $selectAll = $('.payment-filter-select-all');
    const $groupBySelect = $('#group-by-select');

    const moment = window.moment;
    const currency = vars('currency') || '';

    let currentStartDate = '';
    let currentEndDate = '';

    const groupByLabels = {
        month: lang('month'),
        category: lang('category'),
        service: lang('service'),
    };

    /**
     * Initialize the page.
     */
    function init() {
        App.Utils.DateRangeSelector.init($('#reports-activity-page .date-range-selector'), (startDate, endDate) => {
            currentStartDate = startDate;
            currentEndDate = endDate;
            loadMatrix();
        });

        // Keep the payment filter dropdown open when clicking inside it.
        $('.payment-filter-dropdown-menu').on('click', (event) => {
            event.stopPropagation();
        });

        $paymentFilters.on('change', () => {
            updateSelectAllState();
            loadMatrix();
        });

        $selectAll.on('change', () => {
            const isChecked = $selectAll.is(':checked');
            $paymentFilters.prop('checked', isChecked);
            loadMatrix();
        });

        $groupBySelect.on('change', () => {
            loadMatrix();
        });
    }

    /**
     * Update the "select all" checkbox state based on individual checkboxes.
     */
    function updateSelectAllState() {
        const total = $paymentFilters.length;
        const checked = $paymentFilters.filter(':checked').length;

        if (checked === 0) {
            $selectAll.prop('checked', false).prop('indeterminate', false);
        } else if (checked === total) {
            $selectAll.prop('checked', true).prop('indeterminate', false);
        } else {
            $selectAll.prop('checked', false).prop('indeterminate', true);
        }
    }

    /**
     * Collect the currently selected payment statuses.
     *
     * @return {Array}
     */
    function getSelectedPaymentStatuses() {
        return $paymentFilters
            .filter(':checked')
            .map((_, element) => $(element).val())
            .get();
    }

    /**
     * Fetch and render the activity matrix.
     */
    function loadMatrix() {
        if (!currentStartDate || !currentEndDate) {
            return;
        }

        const url = App.Utils.Url.siteUrl('reports/get_activity');
        const paymentStatuses = getSelectedPaymentStatuses();
        const groupBy = $groupBySelect.val();

        const requestData = {
            csrf_token: vars('csrf_token'),
            start_date: currentStartDate,
            end_date: currentEndDate,
            group_by: groupBy,
        };

        if (paymentStatuses.length) {
            requestData.payment_statuses = paymentStatuses;
        }

        $.post(url, requestData)
            .done((response) => {
                $emptyHint.addClass('d-none');
                $matrixSection.removeClass('d-none');

                $matrixRowHeader.text(groupByLabels[groupBy] || lang('month'));

                renderMatrix(
                    response.periods || [],
                    response.provider_totals || {},
                    response.grand_total || { cnt: 0, total: 0.0 },
                    groupBy,
                );
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
     * @param {string} groupBy
     */
    function renderMatrix(periods, providerTotals, grandTotal, groupBy) {
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

                row.append(`<td class="fw-light">${formatRowLabel(period.period, groupBy)}</td>`);

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
     * Format a row label based on the active grouping.
     *
     * @param {string} period
     * @param {string} groupBy
     *
     * @return {string}
     */
    function formatRowLabel(period, groupBy) {
        if (groupBy === 'month') {
            const value = moment(period, 'YYYY-MM');
            return value.isValid() ? value.format('MMMM YYYY') : period;
        }

        return period;
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
