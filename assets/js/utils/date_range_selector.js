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
 * Reusable date range selector.
 *
 * Usage:
 *     App.Utils.DateRangeSelector.init($('.date-range-selector'), function (start, end, label) {
 *         console.log(start, end, label);
 *     });
 */
App.Utils.DateRangeSelector = (function () {
    const moment = window.moment;

    /**
     * Initialize a date range selector inside the given container.
     *
     * @param {jQuery} $container
     * @param {Function} onChange Callback(startDate, endDate, label) with YYYY-MM-DD strings.
     */
    function init($container, onChange) {
        onChange = onChange || function () {};

        const $startInput = $container.find('.date-range-start');
        const $endInput = $container.find('.date-range-end');
        const $form = $container.find('.date-range-form');
        const $presetButtons = $container.find('.range-btn');

        let isApplyingRange = false;

        App.Utils.UI.initializeDatePicker($startInput, {
            dateFormat: 'Y-m-d',
            onChange: () => {
                if (!isApplyingRange) {
                    clearActivePreset();
                }
            },
        });

        App.Utils.UI.initializeDatePicker($endInput, {
            dateFormat: 'Y-m-d',
            onChange: () => {
                if (!isApplyingRange) {
                    clearActivePreset();
                }
            },
        });

        /**
         * Remove active state from all preset buttons.
         */
        function clearActivePreset() {
            $presetButtons.removeClass('active');
        }

        /**
         * Mark a preset button as active.
         *
         * @param {jQuery} $button
         */
        function setActivePreset($button) {
            $presetButtons.removeClass('active');
            $button.addClass('active');
        }

        /**
         * Apply a predefined date range to the inputs and optionally notify the callback.
         *
         * @param {string} range
         * @param {boolean} triggerCallback Whether to invoke the onChange callback.
         * @param {string|null} label Optional label to pass to the callback.
         */
        function applyRange(range, triggerCallback = true, label = null) {
            const today = moment();
            let start = today.clone();
            let end = today.clone();

            switch (range) {
                case 'today':
                    break;
                case 'last_7_days':
                    start = today.clone().subtract(6, 'days');
                    break;
                case 'last_14_days':
                    start = today.clone().subtract(13, 'days');
                    break;
                case 'last_30_days':
                    start = today.clone().subtract(29, 'days');
                    break;
                case 'last_365_days':
                    start = today.clone().subtract(364, 'days');
                    break;
                case 'current_week':
                    start = today.clone().startOf('isoWeek');
                    break;
                case 'last_week':
                    start = today.clone().subtract(1, 'week').startOf('isoWeek');
                    end = today.clone().subtract(1, 'week').endOf('isoWeek');
                    break;
                case 'current_month':
                    start = today.clone().startOf('month');
                    break;
                case 'last_month':
                    start = today.clone().subtract(1, 'month').startOf('month');
                    end = today.clone().subtract(1, 'month').endOf('month');
                    break;
                case 'current_year':
                    start = today.clone().startOf('year');
                    break;
                case 'last_year':
                    start = today.clone().subtract(1, 'year').startOf('year');
                    end = today.clone().subtract(1, 'year').endOf('year');
                    break;
                default:
                    return;
            }

            const startDate = start.format('YYYY-MM-DD');
            const endDate = end.format('YYYY-MM-DD');

            isApplyingRange = true;
            $startInput[0]._flatpickr.setDate(startDate);
            $endInput[0]._flatpickr.setDate(endDate);
            isApplyingRange = false;

            if (triggerCallback) {
                onChange(startDate, endDate, label);
            }
        }

        /**
         * Format a date range for display.
         *
         * @param {string} startDate
         * @param {string} endDate
         *
         * @return {string}
         */
        function formatDateRange(startDate, endDate) {
            return moment(startDate).format('LL') + ' – ' + moment(endDate).format('LL');
        }

        $presetButtons.on('click', function () {
            const $button = $(this);
            const label = $button.text().trim();
            applyRange($button.data('range'), true, label);
            setActivePreset($button);
        });

        $form.on('submit', function (event) {
            event.preventDefault();

            clearActivePreset();

            const startDate = $startInput.val();
            const endDate = $endInput.val();

            if (startDate && endDate) {
                onChange(startDate, endDate, formatDateRange(startDate, endDate));
            }
        });

        // Default selection: current month (prefill inputs only, do not trigger the callback).
        applyRange('current_month', false);
        setActivePreset($presetButtons.filter('[data-range="current_month"]'));
    }

    return {
        init,
    };
})();
