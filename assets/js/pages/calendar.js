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
 * Calendar page.
 *
 * This module implements the functionality of the backend calendar page.
 */
App.Pages.Calendar = (function () {
    const $insertWorkingPlanException = $('#insert-working-plan-exception');

    const moment = window.moment;

    /**
     * Add the page event listeners.
     */
    function addEventListeners() {
        const $calendarPage = $('#calendar-page');

        $calendarPage.on('click', '#toggle-fullscreen', (event) => {
            const $toggleFullscreen = $(event.target);
            const element = document.documentElement;
            const isFullScreen =
                document.fullScreenElement || document.mozFullScreen || document.webkitIsFullScreen || false;

            if (isFullScreen) {
                // Exit fullscreen mode.
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }

                $toggleFullscreen.removeClass('btn-success').addClass('btn-light');
            } else {
                // Switch to fullscreen mode.
                if (element.requestFullscreen) {
                    element.requestFullscreen();
                } else if (element.msRequestFullscreen) {
                    element.msRequestFullscreen();
                } else if (element.mozRequestFullScreen) {
                    element.mozRequestFullScreen();
                } else if (element.webkitRequestFullscreen) {
                    element.webkitRequestFullscreen();
                }
                $toggleFullscreen.removeClass('btn-light').addClass('btn-success');
            }
        });

        $insertWorkingPlanException.on('click', () => {
            const providerId = $('#select-filter-item').val();

            if (providerId === App.Utils.CalendarDefaultView.FILTER_TYPE_ALL) {
                return;
            }

            const provider = vars('available_providers').find((availableProvider) => {
                return Number(availableProvider.id) === Number(providerId);
            });

            if (!provider) {
                throw new Error('Provider could not be found: ' + providerId);
            }

            App.Components.WorkingPlanExceptionsModal.add().done((workingPlanException) => {
                const successCallback = (response) => {
                    App.Layouts.Backend.displayNotification(lang('working_plan_exception_saved'));

                    // Update the in-memory provider data with the new exception
                    let exceptions = JSON.parse(provider.settings.working_plan_exceptions || '[]');
                    if (!Array.isArray(exceptions)) {
                        exceptions = [];
                    }

                    // Add the new exception (with ID from response if available)
                    if (response && response.id) {
                        workingPlanException.id = response.id;
                    }
                    exceptions.push(workingPlanException);
                    provider.settings.working_plan_exceptions = JSON.stringify(exceptions);

                    $('#select-filter-item').trigger('change'); // Update the calendar.
                };

                App.Http.Calendar.saveWorkingPlanException(
                    workingPlanException,
                    providerId,
                    successCallback,
                    null,
                );
            });
        });
    }

    /**
     * Get calendar selection end date.
     *
     * On calendar slot selection, calculate the end date based on the provided start date.
     *
     * @param {Object} info Holding the "start" and "end" props, as provided by FullCalendar.
     *
     * @return {Date}
     */
    function getSelectionEndDate(info) {
        const startMoment = moment(info.start);
        const endMoment = moment(info.end);
        const startTillEndDiff = endMoment.diff(startMoment);
        const startTillEndDuration = moment.duration(startTillEndDiff);
        const durationInMinutes = startTillEndDuration.asMinutes();
        const minDurationInMinutes = 15;

        if (durationInMinutes <= minDurationInMinutes) {
            const serviceId = $('#select-service').val();
            const service = vars('available_services').find(
                (availableService) => Number(availableService.id) === Number(serviceId),
            );

            if (service) {
                endMoment.add(service.duration - durationInMinutes, 'minutes');
            }
        }

        return endMoment.toDate();
    }

    /**
     * Scale only the calendar grid so it fits the mobile viewport width.
     *
     * Layout, fonts and column sizes are left untouched; the calendar block is
     * scaled uniformly with CSS zoom. Header, toolbar and modals stay at their
     * normal size. This is applied once on load only — it does NOT recalculate
     * on pinch-zoom or window resize, so the user remains in full control of
     * manual zoom.
     */
    function fitCalendarToViewport() {
        const $calendar = $('#calendar');

        if (!$calendar.length) {
            return;
        }

        const isMobile = window.matchMedia('(max-width: 767.98px)').matches;

        if (!isMobile) {
            $calendar.css('zoom', '');
            return;
        }

        // The table view renders inside a scrollable container, so its natural
        // width is not reflected in document scroll width. Measure the inner
        // wrapper directly.
        const innerWrapper = $calendar[0].querySelector('.calendar-view > div');

        if (!innerWrapper) {
            $calendar.css('zoom', '');
            return;
        }

        const calendarWidth = innerWrapper.scrollWidth;
        const viewportWidth = window.innerWidth;

        if (calendarWidth <= viewportWidth) {
            $calendar.css('zoom', '');
        } else {
            $calendar.css('zoom', viewportWidth / calendarWidth);
        }
    }

    /**
     * Apply the mobile calendar fit once, after the table view has finished
     * rendering. We wait for the inner table wrapper to appear so the scale is
     * calculated from the real content width, not from the empty skeleton.
     */
    function initCalendarFit() {
        if (!$('#calendar').length) {
            return;
        }

        if (!window.matchMedia('(max-width: 767.98px)').matches) {
            return;
        }

        let attempts = 0;
        const maxAttempts = 30;

        const interval = setInterval(() => {
            attempts++;

            const innerWrapper = document.querySelector('#calendar .calendar-view > div');

            if (innerWrapper && innerWrapper.scrollWidth > 0) {
                fitCalendarToViewport();
                clearInterval(interval);
                return;
            }

            if (attempts >= maxAttempts) {
                clearInterval(interval);
            }
        }, 100);
    }

    /**
     * Initialize the module.
     *
     * This function makes the necessary initialization for the default backend calendar page.
     *
     * If this module is used in another page then this function might not be needed.
     */
    function initialize() {
        // Load and initialize the calendar view.
        if (vars('calendar_view') === 'table') {
            App.Utils.CalendarTableView.initialize();
        } else {
            App.Utils.CalendarDefaultView.initialize();
        }

        App.Pages.Calendar.addEventListeners();
        initCalendarFit();
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        addEventListeners,
        getSelectionEndDate,
    };
})();
