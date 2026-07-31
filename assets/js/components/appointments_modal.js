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
 * Appointments modal component.
 *
 * This module implements the appointments modal functionality.
 *
 * Old Name: BackendCalendarAppointmentsModal
 */
App.Components.AppointmentsModal = (function () {
    const $appointmentsModal = $('#appointments-modal');
    const $startDatetime = $('#start-datetime');
    const $endDatetime = $('#end-datetime');
    const $filterExistingCustomers = $('#filter-existing-customers');
    const $customerId = $('#customer-id');
    const $firstName = $('#first-name');
    const $lastName = $('#last-name');
    const $email = $('#email');
    const $phoneNumber = $('#phone-number');
    const $address = $('#address');
    const $city = $('#city');
    const $zipCode = $('#zip-code');
    const $language = $('#language');
    const $customerNotes = $('#customer-notes');
    const $selectCustomer = $('#select-customer');
    const $saveAppointment = $('#save-appointment');
    const $appointmentId = $('#appointment-id');
    const $appointmentLocation = $('#appointment-location');
    const $appointmentMeetingLink = $('#appointment-meeting-link');
    const $appointmentStatus = $('#appointment-status');
    const $appointmentCloseStatus = $('#appointment-close-status');
    const $appointmentColor = $('#appointment-color');
    const $appointmentNotes = $('#appointment-notes');
    const $reloadAppointments = $('#reload-appointments');
    const $selectFilterItem = $('#select-filter-item');
    const $selectServiceCategory = $('#select-service-category');
    const $selectService = $('#select-service');
    const $selectProvider = $('#select-provider');
    const $selectAppointmentType = $('#select-appointment-type');
    const $customerPackageWrapper = $('#customer-package-wrapper');
    const $selectCustomerPackage = $('#select-customer-package');
    const $servicePriceWrapper = $('#service-price-wrapper');
    const $appointmentPrice = $('#appointment-price');
    const $additionalServices = $('#additional-services');
    const $addAdditionalService = $('#add-additional-service');
    const $appointmentServicesTotal = $('#appointment-services-total');
    const $insertAppointment = $('#insert-appointment');
    const $existingCustomersList = $('#existing-customers-list');
    const $newCustomer = $('#new-customer');
    const $customField1 = $('#custom-field-1');
    const $customField2 = $('#custom-field-2');
    const $customField3 = $('#custom-field-3');
    const $customField4 = $('#custom-field-4');
    const $customField5 = $('#custom-field-5');
    const $depositStatusArea = $('#deposit-status-area');
    const $depositStatusIcon = $('#deposit-status-icon');
    const $depositStatusLabel = $('#deposit-status-label');
    const $depositStatusDetails = $('#deposit-status-details');
    const $sendPaymentLink = $('#send-payment-link');

    const moment = window.moment;

    let customerPackages = [];

    let currentDepositStatus = 'none';

    /**
     * Update the displayed total price for all selected services.
     */
    function updateAppointmentServicesTotal() {
        let total = 0;

        $appointmentsModal.find('.appointment-service-price').each(function () {
            const value = parseFloat($(this).val());

            if (!isNaN(value)) {
                total += value;
            }
        });

        $appointmentServicesTotal.text(total.toFixed(2));
    }

    /**
     * Build the HTML for an additional service row.
     */
    function buildAdditionalServiceRow() {
        const serviceOptions = $selectService.html();

        return `
            <div class="additional-service-row mb-2">
                <div class="row g-2 align-items-center">
                    <div class="col-7">
                        <select class="form-select additional-service-id">
                            ${serviceOptions}
                        </select>
                    </div>
                    <div class="col-4">
                        <input type="number" class="form-control additional-service-price appointment-service-price" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="col-1">
                        <button class="btn btn-outline-danger btn-sm remove-additional-service" type="button">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Add a new additional service row to the form.
     */
    function addAdditionalServiceRow() {
        const $row = $(buildAdditionalServiceRow());

        $additionalServices.append($row);

        const serviceId = $row.find('.additional-service-id').val();

        const service = vars('available_services').find(
            (availableService) => Number(availableService.id) === Number(serviceId),
        );

        const $price = $row.find('.additional-service-price');

        if (service && service.price !== undefined && service.price !== null && !$price.val()) {
            $price.val(service.price);
        }

        updateAppointmentServicesTotal();
    }

    /**
     * Remove all additional service rows.
     */
    function clearAdditionalServices() {
        $additionalServices.empty();
        updateAppointmentServicesTotal();
    }

    /**
     * Update the displayed timezone.
     */
    function updateTimezone() {
        if (vars('default_timezone')) {
            $('.provider-timezone').text(vars('timezones')[vars('default_timezone')]);
        }
    }

    /**
     * Find a service by ID.
     *
     * @param {number} serviceId
     * @returns {Object|undefined}
     */
    function findService(serviceId) {
        return vars('available_services').find((availableService) => Number(availableService.id) === Number(serviceId));
    }

    /**
     * Load active customer packages for the selected customer.
     *
     * @param {Number} customerId
     *
     * @return {Object} jQuery promise
     */
    function loadCustomerPackages(customerId, selectedValue = null) {
        $selectCustomerPackage.empty().append(new Option(lang('please_select'), ''));
        customerPackages = [];

        if (!customerId) {
            return $.Deferred().resolve().promise();
        }

        return App.Http.CustomerPackages.searchByCustomer(customerId, null)
            .done((response) => {
                customerPackages = response || [];
                renderCustomerPackageOptions(selectedValue);
            })
            .fail(() => {
                customerPackages = [];
            });
    }

    /**
     * Render the customer package selector options.
     *
     * @param {String|null} selectedValue The currently selected "packageId|serviceId" value (used to keep
     *                                     consumed items visible while editing an appointment).
     */
    function renderCustomerPackageOptions(selectedValue = null) {
        const [selectedPackageId, selectedServiceId] = selectedValue
            ? selectedValue.split('|').map(Number)
            : [null, null];

        $selectCustomerPackage.empty().append(new Option(lang('please_select'), ''));

        customerPackages.forEach((customerPackage) => {
            if (!customerPackage.items) {
                return;
            }

            const isSelectedPackage = Number(customerPackage.id) === selectedPackageId;

            // Skip inactive packages unless the appointment is already tied to this package.
            if (!customerPackage.is_active && !isSelectedPackage) {
                return;
            }

            customerPackage.items.forEach((item) => {
                const value = `${customerPackage.id}|${item.id_services}`;
                const isSelected =
                    isSelectedPackage && Number(item.id_services) === selectedServiceId;

                // Show items with remaining quantity, plus the currently selected item even
                // if it was already consumed (so the editor does not lose the link).
                if (Number(item.quantity_remaining) <= 0 && !isSelected) {
                    return;
                }

                const remaining = Number(item.quantity_remaining);
                const label = `${customerPackage.package_name} - ${item.service_name} (${remaining} ${lang('remaining')})`;

                $selectCustomerPackage.append(new Option(label, value));
            });
        });
    }

    /**
     * Handle customer selection / change.
     */
    function onCustomerSelected() {
        const customerId = $customerId.val();

        $selectAppointmentType.val('service').trigger('change');

        loadCustomerPackages(customerId);
    }

    /**
     * Toggle package selector visibility based on appointment type.
     */
    function onAppointmentTypeChange() {
        if ($selectAppointmentType.val() === 'package') {
            $customerPackageWrapper.slideDown('fast');
            $servicePriceWrapper.slideUp('fast');
            $appointmentPrice.val(0).trigger('input');
            clearAdditionalServices();
        } else {
            $customerPackageWrapper.slideUp('fast');
            $servicePriceWrapper.slideDown('fast');
            $selectCustomerPackage.val('').trigger('change');

            // Restore the service default price when switching back to a normal service appointment.
            const service = findService($selectService.val());

            if (service && service.price !== undefined && service.price !== null) {
                $appointmentPrice.val(service.price).trigger('input');
            }
        }
    }

    /**
     * Apply the selected customer package to the appointment form.
     */
    function onCustomerPackageChange() {
        const selectedValue = $selectCustomerPackage.val();

        if (!selectedValue) {
            return;
        }

        const [packageId, serviceId] = selectedValue.split('|').map(Number);

        const service = findService(serviceId);

        if (!service) {
            return;
        }

        const serviceCategoryName = service?.service_category_name || 'uncategorized';
        $selectServiceCategory.val(serviceCategoryName).trigger('change');
        $selectService.val(serviceId).trigger('change');

        $appointmentPrice.val(0).trigger('input');
    }

    /**
     * Add the component event listeners.
     */
    function addEventListeners() {
        /**
         * Event: Manage Appointments Dialog Save Button "Click"
         *
         * Stores the appointment changes or inserts a new appointment depending on the dialog mode.
         */
        $saveAppointment.on('click', () => {
            // Before doing anything the appointment data need to be validated.
            if (!App.Components.AppointmentsModal.validateAppointmentForm()) {
                return;
            }

            // ID must exist on the object in order for the model to update the record and not to perform
            // an insert operation.

            const startDateTimeObject = App.Utils.UI.getDateTimePickerValue($startDatetime);
            const startDatetime = moment(startDateTimeObject).format('YYYY-MM-DD HH:mm:ss');

            const endDateTimeObject = App.Utils.UI.getDateTimePickerValue($endDatetime);
            const endDatetime = moment(endDateTimeObject).format('YYYY-MM-DD HH:mm:ss');

            // The closing/payment status (Închide programare) takes precedence when set,
            // otherwise fall back to the regular status dropdown.
            const appointmentStatus = $appointmentCloseStatus.val() || $appointmentStatus.val();

            const customerPackageValue =
                $selectAppointmentType.val() === 'package'
                    ? ($selectCustomerPackage.val() || '').split('|')[0] || null
                    : null;

            const baseAppointment = {
                id_users_provider: $selectProvider.val(),
                location: $appointmentLocation.val(),
                meeting_link: $appointmentMeetingLink.val(),
                color: App.Components.ColorSelection.getColor($appointmentColor),
                status: appointmentStatus,
                notes: $appointmentNotes.val(),
                is_unavailability: Number(false),
                id_customer_packages: customerPackageValue,
            };

            const buildAppointment = (serviceId, price, start, end) => ({
                ...baseAppointment,
                id_services: serviceId,
                price: price || null,
                start_datetime: start,
                end_datetime: end,
            });

            let currentStart = moment(startDateTimeObject);
            let currentEnd = moment(endDateTimeObject);

            const appointments = [
                buildAppointment($selectService.val(), $appointmentPrice.val(), startDatetime, endDatetime),
            ];

            $additionalServices.find('.additional-service-row').each(function () {
                const $row = $(this);
                const serviceId = $row.find('.additional-service-id').val();
                const price = $row.find('.additional-service-price').val();

                if (!serviceId) {
                    return; // continue
                }

                currentStart = currentEnd.clone();

                const service = vars('available_services').find(
                    (availableService) => Number(availableService.id) === Number(serviceId),
                );

                const duration = service ? service.duration : 60;

                currentEnd = currentStart.clone().add(duration, 'minutes');

                appointments.push(
                    buildAppointment(
                        serviceId,
                        price,
                        currentStart.format('YYYY-MM-DD HH:mm:ss'),
                        currentEnd.format('YYYY-MM-DD HH:mm:ss'),
                    ),
                );
            });

            if ($appointmentId.val() !== '') {
                // Set the id value, only if we are editing an appointment.
                appointments[0].id = $appointmentId.val();
            }

            const appointment = appointments.length === 1 ? appointments[0] : appointments;

            const customer = {
                first_name: $firstName.val(),
                last_name: $lastName.val(),
                email: $email.val(),
                phone_number: $phoneNumber.val(),
                address: $address.val(),
                city: $city.val(),
                zip_code: $zipCode.val(),
                language: $language.val(),
                notes: $customerNotes.val(),
                custom_field_1: $customField1.val(),
                custom_field_2: $customField2.val(),
                custom_field_3: $customField3.val(),
                custom_field_4: $customField4.val(),
                custom_field_5: $customField5.val(),
            };

            if ($customerId.val() !== '') {
                // Set the id value, only if we are editing an appointment.
                customer.id = $customerId.val();
                appointment.id_users_customer = customer.id;
            }

            // Define success callback.
            const successCallback = () => {
                // Display success message to the user.
                App.Layouts.Backend.displayNotification(lang('appointment_saved'));

                // Close the modal dialog and refresh the calendar appointments.
                $appointmentsModal.find('.alert').addClass('d-none');
                $appointmentsModal.modal('hide');
                $reloadAppointments.trigger('click');
            };

            // Define error callback.
            const errorCallback = () => {
                $appointmentsModal.find('.modal-message').text(lang('service_communication_error'));
                $appointmentsModal.find('.modal-message').addClass('alert-danger').removeClass('d-none');
                $appointmentsModal.find('.modal-body').scrollTop(0);
            };

            // Check if this is an update (appointment has an ID)
            const isUpdate = Boolean(appointment.id);

            if (isUpdate) {
                // Show confirmation dialog for notification preference
                App.Utils.Message.show(lang('appointment_update'), lang('notify_users_on_update_question'), [
                    {
                        text: lang('no'),
                        click: (event, messageModal) => {
                            messageModal.hide();
                            App.Http.Calendar.saveAppointmentWithConflictHandling(
                                appointment,
                                customer,
                                successCallback,
                                errorCallback,
                                false,
                            );
                        },
                    },
                    {
                        text: lang('yes'),
                        click: (event, messageModal) => {
                            messageModal.hide();
                            App.Http.Calendar.saveAppointmentWithConflictHandling(
                                appointment,
                                customer,
                                successCallback,
                                errorCallback,
                                true,
                            );
                        },
                    },
                ]);
            } else {
                // New appointment - ask whether to notify users
                App.Utils.Message.show(lang('new_appointment_title'), lang('notify_users_on_create_question'), [
                    {
                        text: lang('no'),
                        click: (event, messageModal) => {
                            messageModal.hide();
                            App.Http.Calendar.saveAppointmentWithConflictHandling(
                                appointment,
                                customer,
                                successCallback,
                                errorCallback,
                                false,
                            );
                        },
                    },
                    {
                        text: lang('yes'),
                        click: (event, messageModal) => {
                            messageModal.hide();
                            App.Http.Calendar.saveAppointmentWithConflictHandling(
                                appointment,
                                customer,
                                successCallback,
                                errorCallback,
                                true,
                            );
                        },
                    },
                ]);
            }
        });

        /**
         * Event: Send Payment Link Button "Click"
         *
         * Creates a Stripe Checkout Session for the appointment deposit and sends
         * the payment link to the customer via WhatsApp.
         */
        $sendPaymentLink.on('click', () => {
            const appointmentId = $appointmentId.val();

            if (!appointmentId) {
                return;
            }

            $sendPaymentLink
                .prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>' + lang('sending_payment_link'));

            App.Http.Payments.createCheckoutSession(appointmentId)
                .done((response) => {
                    if (response && response.success) {
                        if (response.whatsapp && response.whatsapp.success === false) {
                            App.Layouts.Backend.displayNotification(lang('payment_link_whatsapp_error'));
                        } else {
                            App.Layouts.Backend.displayNotification(lang('payment_link_sent'));
                        }

                        updateDepositUi({
                            id: appointmentId,
                            deposit_status: 'unpaid',
                            payment_link_sent_at: moment().format('YYYY-MM-DD HH:mm:ss'),
                        });
                    } else {
                        $appointmentsModal
                            .find('.modal-message')
                            .text((response && response.message) || lang('payment_link_error'))
                            .addClass('alert-danger')
                            .removeClass('d-none');
                        $appointmentsModal.find('.modal-body').scrollTop(0);
                    }
                })
                .fail(() => {
                    $appointmentsModal
                        .find('.modal-message')
                        .text(lang('payment_link_error'))
                        .addClass('alert-danger')
                        .removeClass('d-none');
                    $appointmentsModal.find('.modal-body').scrollTop(0);
                })
                .always(() => {
                    $sendPaymentLink.prop('disabled', false);
                    setPaymentLinkButtonLabel();
                });
        });

        /**
         * Event: Insert Appointment Button "Click"
         *
         * When the user presses this button, the manage appointment dialog opens and lets the user create a new
         * appointment.
         */
        $insertAppointment.on('click', () => {
            $('.popover').remove();

            App.Components.AppointmentsModal.add();
        });

        /**
         * Event: Pick Existing Customer Button "Click"
         *
         * @param {jQuery.Event} event
         */
        $selectCustomer.on('click', (event) => {
            if (!$existingCustomersList.is(':visible')) {
                $(event.currentTarget).find('span').text(lang('hide'));
                $existingCustomersList.empty();
                $existingCustomersList.slideDown('slow');
                $filterExistingCustomers.fadeIn('slow').val('');
                vars('customers').forEach((customer) => {
                    $('<div/>', {
                        'data-id': customer.id,
                        'text':
                            (customer.first_name || '[No First Name]') + ' ' + (customer.last_name || '[No Last Name]'),
                    }).appendTo($existingCustomersList);
                });
            } else {
                $existingCustomersList.slideUp('slow');
                $filterExistingCustomers.fadeOut('slow');
                $(event.currentTarget).find('span').text(lang('select'));
            }
        });

        /**
         * Event: Select Existing Customer From List "Click"
         *
         * @param {jQuery.Event}
         */
        $appointmentsModal.on('click', '#existing-customers-list div', (event) => {
            const customerId = $(event.target).attr('data-id');

            const customer = vars('customers').find((customer) => Number(customer.id) === Number(customerId));

            if (customer) {
                $customerId.val(customer.id);
                $firstName.val(customer.first_name);
                $lastName.val(customer.last_name);
                $email.val(customer.email);
                $phoneNumber.val(customer.phone_number);
                $address.val(customer.address);
                $city.val(customer.city);
                $zipCode.val(customer.zip_code);
                $language.val(customer.language);
                $customerNotes.val(customer.notes);
                $customField1.val(customer.custom_field_1);
                $customField2.val(customer.custom_field_2);
                $customField3.val(customer.custom_field_3);
                $customField4.val(customer.custom_field_4);
                $customField5.val(customer.custom_field_5);
            }

            $selectCustomer.trigger('click'); // Hide the list.

            onCustomerSelected();
        });

        let filterExistingCustomersTimeout = null;

        /**
         * Event: Filter Existing Customers "Change"
         *
         * @param {jQuery.Event}
         */
        $filterExistingCustomers.on('keyup', (event) => {
            if (filterExistingCustomersTimeout) {
                clearTimeout(filterExistingCustomersTimeout);
            }

            const keyword = $(event.target).val().toLowerCase();

            filterExistingCustomersTimeout = setTimeout(() => {
                $('#loading').css('visibility', 'hidden');

                App.Http.Customers.search(keyword, 50)
                    .done((response) => {
                        $existingCustomersList.empty();

                        response.forEach((customer) => {
                            $('<div/>', {
                                'data-id': customer.id,
                                'text':
                                    (customer.first_name || '[No First Name]') +
                                    ' ' +
                                    (customer.last_name || '[No Last Name]'),
                            }).appendTo($existingCustomersList);

                            // Verify if this customer is on the old customer list.
                            const result = vars('customers').filter((existingCustomer) => {
                                return Number(existingCustomer.id) === Number(customer.id);
                            });

                            // Add it to the customer list.
                            if (!result.length) {
                                vars('customers').push(customer);
                            }
                        });
                    })
                    .fail(() => {
                        // If there is any error on the request, search by the local client database.
                        $existingCustomersList.empty();

                        vars('customers').forEach((customer) => {
                            if (
                                customer.first_name.toLowerCase().indexOf(keyword) !== -1 ||
                                customer.last_name.toLowerCase().indexOf(keyword) !== -1 ||
                                customer.email.toLowerCase().indexOf(keyword) !== -1 ||
                                customer.phone_number.toLowerCase().indexOf(keyword) !== -1 ||
                                customer.address.toLowerCase().indexOf(keyword) !== -1 ||
                                customer.city.toLowerCase().indexOf(keyword) !== -1 ||
                                customer.zip_code.toLowerCase().indexOf(keyword) !== -1 ||
                                customer.notes.toLowerCase().indexOf(keyword) !== -1
                            ) {
                                $('<div/>', {
                                    'data-id': customer.id,
                                    'text':
                                        (customer.first_name || '[No First Name]') +
                                        ' ' +
                                        (customer.last_name || '[No Last Name]'),
                                }).appendTo($existingCustomersList);
                            }
                        });
                    })
                    .always(() => {
                        $('#loading').css('visibility', '');
                    });
            }, 1000);
        });

        /**
         * Event: Selected Service "Change"
         *
         * When the user clicks on a service, its available providers should become visible. We also need to
         * update the start and end time of the appointment.
         */
        /**
         * Event: Close Status "Change"
         *
         * Selecting a closing status updates the main appointment status field.
         */
        $appointmentCloseStatus.on('change', () => {
            const closeStatus = $appointmentCloseStatus.val();

            if (closeStatus) {
                $appointmentStatus.val(closeStatus);
            }
        });

        /**
         * Event: Appointment Status "Change"
         *
         * If the selected status is a closing status, keep the close-status dropdown in sync.
         */
        $appointmentStatus.on('change', () => {
            const status = $appointmentStatus.val();
            const closeStatusOption = $appointmentCloseStatus.find('option[value="' + status + '"]');

            if (closeStatusOption.length) {
                $appointmentCloseStatus.val(status);
            } else {
                $appointmentCloseStatus.val('');
            }
        });

        $selectServiceCategory.on('change', () => {
            const categoryName = $selectServiceCategory.val();

            $selectService.empty();
            $selectService.append(new Option(lang('please_select'), ''));

            if (!categoryName) {
                $selectService.trigger('change');
                return;
            }

            vars('available_services').forEach((service) => {
                const serviceCategoryName = service.service_category_name || '';
                const isUncategorized = !service.service_category_id;

                if (serviceCategoryName === categoryName || (categoryName === 'uncategorized' && isUncategorized)) {
                    $selectService.append(new Option(service.name, service.id));
                }
            });

            $selectService.trigger('change');
        });

        $selectService.on('change', () => {
            const serviceId = $selectService.val();

            const providerId = $selectProvider.val();

            $selectProvider.empty();

            // Automatically update the service duration.
            const service = vars('available_services').find((availableService) => {
                return Number(availableService.id) === Number(serviceId);
            });

            if (service?.color) {
                App.Components.ColorSelection.setColor($appointmentColor, service.color);
            }

            // Update the appointment price to the selected service's default price,
            // unless this is a customer package appointment where the price must stay 0.
            if (service && service.price !== undefined && service.price !== null) {
                if ($selectAppointmentType.val() === 'package') {
                    $appointmentPrice.val(0);
                } else {
                    $appointmentPrice.val(service.price);
                }
            }

            updateAppointmentServicesTotal();

            const duration = service ? service.duration : 60;

            const startDateTimeObject = App.Utils.UI.getDateTimePickerValue($startDatetime);
            const endDateTimeObject = new Date(startDateTimeObject.getTime() + duration * 60000);
            App.Utils.UI.setDateTimePickerValue($endDatetime, endDateTimeObject);

            // Update the providers select box.

            vars('available_providers').forEach((provider) => {
                provider.services.forEach((providerServiceId) => {
                    if (
                        vars('role_slug') === App.Layouts.Backend.DB_SLUG_PROVIDER &&
                        Number(provider.id) !== vars('user_id')
                    ) {
                        return; // continue
                    }

                    if (
                        vars('role_slug') === App.Layouts.Backend.DB_SLUG_SECRETARY &&
                        vars('secretary_providers').indexOf(Number(provider.id)) === -1
                    ) {
                        return; // continue
                    }

                    // If the current provider is able to provide the selected service, add him to the list box.
                    if (Number(providerServiceId) === Number(serviceId)) {
                        $selectProvider.append(new Option(provider.first_name + ' ' + provider.last_name, provider.id));
                    }
                });

                if ($selectProvider.find(`option[value="${providerId}"]`).length) {
                    $selectProvider.val(providerId);
                }
            });
        });

        /**
         * Event: Provider "Change"
         */
        $selectProvider.on('change', () => {
            updateTimezone();
        });

        /**
         * Event: Appointment Type "Change"
         */
        $selectAppointmentType.on('change', () => {
            onAppointmentTypeChange();
        });

        /**
         * Event: Customer Package "Change"
         */
        $selectCustomerPackage.on('change', () => {
            onCustomerPackageChange();
        });

        /**
         * Event: Add Additional Service Button "Click"
         */
        $addAdditionalService.on('click', () => {
            addAdditionalServiceRow();
        });

        /**
         * Event: Remove Additional Service Button "Click"
         */
        $additionalServices.on('click', '.remove-additional-service', (event) => {
            $(event.currentTarget).closest('.additional-service-row').remove();
            updateAppointmentServicesTotal();
        });

        /**
         * Event: Additional Service "Change"
         */
        $additionalServices.on('change', '.additional-service-id', (event) => {
            const serviceId = $(event.currentTarget).val();

            const service = vars('available_services').find(
                (availableService) => Number(availableService.id) === Number(serviceId),
            );

            const $price = $(event.currentTarget).closest('.additional-service-row').find('.additional-service-price');

            if (service && service.price !== undefined && service.price !== null) {
                $price.val(service.price);
            }

            updateAppointmentServicesTotal();
        });

        /**
         * Event: Service Price "Input"
         */
        $appointmentsModal.on('input', '.appointment-service-price', () => {
            updateAppointmentServicesTotal();
        });

        /**
         * Event: Enter New Customer Button "Click"
         */
        $newCustomer.on('click', () => {
            $customerId.val('');
            $firstName.val('');
            $lastName.val('');
            $email.val('');
            $phoneNumber.val('');
            $address.val('');
            $city.val('');
            $zipCode.val('');
            $language.val(vars('default_language'));
            $customerNotes.val('');
            $customField1.val('');
            $customField2.val('');
            $customField3.val('');
            $customField4.val('');
            $customField5.val('');

            $selectAppointmentType.val('service').trigger('change');
            customerPackages = [];
            $selectCustomerPackage.empty().append(new Option(lang('please_select'), ''));
        });
    }

    /**
     * Open the appointments modal to add a new appointment.
     *
     * @param {Object} options Optional preselection values (start, end, serviceId, providerId).
     */
    function add(options = {}) {
        resetModal();

        $appointmentsModal.find('.modal-header h3').text(lang('new_appointment_title'));

        let serviceId = options.serviceId ?? null;
        let providerId = options.providerId ?? null;

        if (!serviceId && !providerId) {
            const filterType = $selectFilterItem.find('option:selected').attr('type');

            if (filterType === 'provider') {
                providerId = $selectFilterItem.val();

                const provider = vars('available_providers').find(
                    (availableProvider) => Number(availableProvider.id) === Number(providerId),
                );

                if (provider && provider.services && provider.services.length) {
                    serviceId = provider.services[0];
                }
            } else if (filterType === 'service') {
                serviceId = $selectFilterItem.val();
            }
        }

        if (serviceId) {
            const service = findService(serviceId);
            const serviceCategoryName = service?.service_category_name || 'uncategorized';
            $selectServiceCategory.val(serviceCategoryName).trigger('change');
            $selectService.val(serviceId).trigger('change');
        } else {
            $selectService.find('option:first').prop('selected', true).trigger('change');
        }

        if (providerId) {
            $selectProvider.val(providerId);
        }

        if (!$selectProvider.val()) {
            $selectProvider.find('option:first').prop('selected', true);
        }

        $selectProvider.trigger('change');

        const service = findService($selectService.val());
        const duration = service ? service.duration : 60;

        if (service && service.price !== undefined && service.price !== null && !$appointmentPrice.val()) {
            $appointmentPrice.val(service.price);
        }

        updateAppointmentServicesTotal();

        let startMoment;

        if (options.start) {
            startMoment = moment(options.start);
        } else {
            startMoment = moment();

            const currentMin = parseInt(startMoment.format('mm'));

            if (currentMin > 0 && currentMin < 15) {
                startMoment.set({minutes: 15});
            } else if (currentMin > 15 && currentMin < 30) {
                startMoment.set({minutes: 30});
            } else if (currentMin > 30 && currentMin < 45) {
                startMoment.set({minutes: 45});
            } else {
                startMoment.add(1, 'hour').set({minutes: 0});
            }
        }

        App.Utils.UI.setDateTimePickerValue($startDatetime, startMoment.toDate());

        const endMoment = options.end ? moment(options.end) : startMoment.clone().add(duration, 'minutes');

        App.Utils.UI.setDateTimePickerValue($endDatetime, endMoment.toDate());

        if ($customerId.val()) {
            loadCustomerPackages($customerId.val());
        }

        $appointmentsModal.modal('show');
    }

    /**
     * Set the payment link button label according to the current deposit status.
     */
    function setPaymentLinkButtonLabel() {
        const labelKey = currentDepositStatus === 'unpaid' ? 'resend_payment_link' : 'send_payment_link';

        $sendPaymentLink.html('<i class="fas fa-credit-card me-2"></i>' + lang(labelKey));
    }

    /**
     * Update the deposit payment UI (badge, payment link button, quick-cancel)
     * based on the deposit fields of the edited appointment.
     *
     * @param {Object} appointment Appointment data (may be empty for new appointments).
     */
    function updateDepositUi(appointment) {
        const depositStatus = appointment.deposit_status || 'none';

        currentDepositStatus = depositStatus;

        $depositStatusArea.addClass('d-none').removeClass('alert-success alert-warning alert-danger');
        $sendPaymentLink.hide();

        if (!appointment.id) {
            return;
        }

        if (depositStatus === 'paid') {
            const details = [];

            if (appointment.deposit_amount) {
                details.push(appointment.deposit_amount + ' Lei');
            }

            if (appointment.deposit_paid_at) {
                details.push(moment(appointment.deposit_paid_at).format('DD.MM.YYYY HH:mm'));
            }

            $depositStatusIcon.attr('class', 'fas fa-check-circle me-2');
            $depositStatusLabel.text(lang('deposit_paid'));
            $depositStatusDetails.text(details.join(' · '));
            $depositStatusArea.removeClass('d-none').addClass('alert-success');
        } else if (depositStatus === 'unpaid' && appointment.deposit_unpaid_alerted_at) {
            // Auto-cancelled for non-payment (24h unpaid, processed by the cron job).
            // The payment link button stays available so the manager can resend it
            // after reverting the cancellation.
            $depositStatusIcon.attr('class', 'fas fa-ban me-2');
            $depositStatusLabel.text(lang('deposit_auto_cancelled'));
            $depositStatusDetails.text(
                appointment.payment_link_sent_at ? moment(appointment.payment_link_sent_at).format('DD.MM.YYYY HH:mm') : '',
            );
            $depositStatusArea.removeClass('d-none').addClass('alert-danger');

            setPaymentLinkButtonLabel();
            $sendPaymentLink.show();
        } else {
            if (depositStatus === 'unpaid') {
                $depositStatusIcon.attr('class', 'fas fa-hourglass-half me-2');
                $depositStatusLabel.text(lang('deposit_pending'));
                $depositStatusDetails.text(
                    appointment.payment_link_sent_at ? moment(appointment.payment_link_sent_at).format('DD.MM.YYYY HH:mm') : '',
                );
                $depositStatusArea.removeClass('d-none').addClass('alert-warning');
            }

            setPaymentLinkButtonLabel();
            $sendPaymentLink.show();
        }
    }

    /**
     * Open the appointments modal to edit an existing appointment.
     *
     * @param {Object} appointment
     */
    function edit(appointment) {
        resetModal();

        $appointmentsModal.find('.modal-header h3').text(lang('edit_appointment_title'));

        $appointmentId.val(appointment.id);

        const service = findService(appointment.id_services);
        const serviceCategoryName = service?.service_category_name || 'uncategorized';
        $selectServiceCategory.val(serviceCategoryName).trigger('change');
        $selectService.val(appointment.id_services).trigger('change');
        $selectProvider.val(appointment.id_users_provider);

        App.Utils.UI.setDateTimePickerValue($startDatetime, moment(appointment.start_datetime).toDate());
        App.Utils.UI.setDateTimePickerValue($endDatetime, moment(appointment.end_datetime).toDate());

        const customer = appointment.customer;

        $customerId.val(appointment.id_users_customer);
        $firstName.val(customer.first_name);
        $lastName.val(customer.last_name);
        $email.val(customer.email);
        $phoneNumber.val(customer.phone_number);
        $address.val(customer.address);
        $city.val(customer.city);
        $zipCode.val(customer.zip_code);
        $language.val(customer.language);
        $customerNotes.val(customer.notes);
        $customField1.val(customer.custom_field_1);
        $customField2.val(customer.custom_field_2);
        $customField3.val(customer.custom_field_3);
        $customField4.val(customer.custom_field_4);
        $customField5.val(customer.custom_field_5);

        $appointmentLocation.val(appointment.location);
        $appointmentMeetingLink.val(appointment.meeting_link);
        $appointmentStatus.val(appointment.status);
        $appointmentCloseStatus.val(appointment.status);
        $appointmentPrice.val(appointment.price ?? '').trigger('input');
        $appointmentNotes.val(appointment.notes);
        App.Components.ColorSelection.setColor($appointmentColor, appointment.color);

        $selectAppointmentType.val(appointment.id_customer_packages ? 'package' : 'service').trigger('change');

        updateDepositUi(appointment);

        const selectedPackageValue = appointment.id_customer_packages
            ? `${appointment.id_customer_packages}|${appointment.id_services}`
            : null;

        loadCustomerPackages(appointment.id_users_customer, selectedPackageValue).always(() => {
            if (selectedPackageValue) {
                $selectCustomerPackage.val(selectedPackageValue);
            }

            $appointmentsModal.modal('show');
        });
    }

    /**
     * Reset Appointment Dialog
     *
     * This method resets the manage appointment dialog modal to its initial state. After that you can make
     * any modification might be necessary in order to bring the dialog to the desired state.
     */
    function resetModal() {
        // Empty form fields.
        $appointmentsModal.find('input, textarea').val('');
        $appointmentsModal.find('.modal-message').addClass('.d-none');
        $appointmentsModal.find('.is-invalid').removeClass('is-invalid');

        clearAdditionalServices();

        const defaultStatusValue = $appointmentStatus.find('option:first').val();
        $appointmentStatus.val(defaultStatusValue);
        $appointmentCloseStatus.val('');

        $selectAppointmentType.val('service');
        $customerPackageWrapper.hide();
        $selectCustomerPackage.empty().append(new Option(lang('please_select'), ''));
        customerPackages = [];

        $language.val(vars('default_language'));

        // Reset color.
        $appointmentColor.find('.color-selection-option:first').trigger('click');

        // Prepare service and provider select boxes.
        $selectServiceCategory.val('');
        $selectService.empty().append(new Option(lang('please_select'), ''));

        // Fill the providers list box with providers that can serve the appointment's service and then select the
        // user's provider.
        $selectProvider.empty();
        vars('available_providers').forEach((provider) => {
            const serviceId = $selectService.val();

            const canProvideService =
                provider.services.filter((providerServiceId) => {
                    return Number(providerServiceId) === Number(serviceId);
                }).length > 0;

            if (canProvideService) {
                // Add the provider to the list box.
                $selectProvider.append(new Option(provider.first_name + ' ' + provider.last_name, provider.id));
            }
        });

        // Close existing customers-filter frame.
        $existingCustomersList.slideUp('slow');
        $filterExistingCustomers.fadeOut('slow');
        $selectCustomer.find('span').text(lang('select'));

        // Setup start and datetimepickers.
        // Get the selected service duration. It will be needed in order to calculate the appointment end datetime.
        const serviceId = $selectService.val();

        const service = vars('available_services').forEach((service) => Number(service.id) === Number(serviceId));

        const duration = service ? service.duration : 0;

        const startDatetime = new Date();
        const endDatetime = moment().add(duration, 'minutes').toDate();

        App.Utils.UI.initializeDateTimePicker($startDatetime, {
            onClose: () => {
                const serviceId = $selectService.val();

                // Automatically update the #end-datetime DateTimePicker based on service duration.
                const service = vars('available_services').find(
                    (availableService) => Number(availableService.id) === Number(serviceId),
                );

                const startDateTimeObject = App.Utils.UI.getDateTimePickerValue($startDatetime);
                const endDateTimeObject = new Date(startDateTimeObject.getTime() + service.duration * 60000);
                App.Utils.UI.setDateTimePickerValue($endDatetime, endDateTimeObject);
            },
        });

        App.Utils.UI.setDateTimePickerValue($startDatetime, startDatetime);

        App.Utils.UI.initializeDateTimePicker($endDatetime);
        App.Utils.UI.setDateTimePickerValue($endDatetime, endDatetime);
        $appointmentsModal.find('.modal-message').removeClass('alert-danger').text('').addClass('d-none');

        updateDepositUi({});

        updateAppointmentServicesTotal();
    }

    /**
     * Check whether the raw value typed into a flatpickr input can be parsed
     * with the configured date/time format. Empty values are considered invalid
     * here because the datetime fields are required.
     *
     * The check is strict: values such as "25:99" that flatpickr would silently
     * roll over to a different date/time are rejected.
     *
     * @param {jQuery} $target
     *
     * @return {Boolean}
     */
    function isDateTimeInputValueValid($target) {
        const flatpickrInstance = $target[0]._flatpickr;
        const inputValue = $target.val();

        if (!inputValue) {
            return false;
        }

        const parsedDate = flatpickrInstance.parseDate(inputValue, flatpickrInstance.config.dateFormat);

        if (!parsedDate) {
            return false;
        }

        const formattedDate = flatpickrInstance.formatDate(parsedDate, flatpickrInstance.config.dateFormat);

        return formattedDate === inputValue;
    }

    /**
     * Validate the manage appointment dialog data.
     *
     * Validation checks need to run every time the data are going to be saved.
     *
     * @return {Boolean} Returns the validation result.
     */
    function validateAppointmentForm() {
        // Reset previous validation css formatting.
        $appointmentsModal.find('.is-invalid').removeClass('is-invalid');
        $appointmentsModal.find('.modal-message').addClass('d-none');

        try {
            // Check required fields.
            let missingRequiredField = false;

            $appointmentsModal.find('.required').each((index, requiredField) => {
                if ($(requiredField).val() === '' || $(requiredField).val() === null) {
                    $(requiredField).addClass('is-invalid');
                    missingRequiredField = true;
                }
            });

            if (missingRequiredField) {
                throw new Error(lang('fields_are_required'));
            }

            // Check email address.
            if (
                $appointmentsModal.find('#email').val() &&
                !App.Utils.Validation.email($appointmentsModal.find('#email').val())
            ) {
                $appointmentsModal.find('#email').addClass('is-invalid');
                throw new Error(lang('invalid_email'));
            }

            // Check appointment start and end time.
            let startDateTimeObject = App.Utils.UI.getDateTimePickerValue($startDatetime);
            let endDateTimeObject = App.Utils.UI.getDateTimePickerValue($endDatetime);

            if (!startDateTimeObject || !endDateTimeObject) {
                $startDatetime.addClass('is-invalid');
                $endDatetime.addClass('is-invalid');
                throw new Error(lang('invalid_datetime'));
            }

            if (
                !isDateTimeInputValueValid($startDatetime) ||
                !isDateTimeInputValueValid($endDatetime)
            ) {
                $startDatetime.addClass('is-invalid');
                $endDatetime.addClass('is-invalid');
                throw new Error(lang('invalid_datetime'));
            }

            // When the user types a new start time directly into the input and
            // saves without closing the picker, the end datetime may still hold
            // the previous value. Recalculate it from the service duration so the
            // appointment remains valid and consistent with the picker behaviour.
            if (startDateTimeObject > endDateTimeObject) {
                const serviceId = $selectService.val();
                const service = vars('available_services').find(
                    (availableService) => Number(availableService.id) === Number(serviceId),
                );
                const duration = service ? service.duration : 60;
                endDateTimeObject = new Date(startDateTimeObject.getTime() + duration * 60000);
                App.Utils.UI.setDateTimePickerValue($endDatetime, endDateTimeObject);
            }

            if (startDateTimeObject > endDateTimeObject) {
                $startDatetime.addClass('is-invalid');
                $endDatetime.addClass('is-invalid');
                throw new Error(lang('start_date_before_end_error'));
            }

            return true;
        } catch (error) {
            $appointmentsModal
                .find('.modal-message')
                .addClass('alert-danger')
                .text(error.message)
                .removeClass('d-none');
            return false;
        }
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        addEventListeners();
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        add,
        edit,
        resetModal,
        validateAppointmentForm,
    };
})();
