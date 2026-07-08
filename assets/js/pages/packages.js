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
 * Packages page client-side logic.
 *
 * Handles listing, filtering, add/edit/delete and dynamic service item rows.
 */
App.Pages.Packages = (function () {
    const $packages = $('#packages-page');
    const $filterPackages = $('#filter-packages');
    const $id = $('#package-id');
    const $name = $('#package-name');
    const $price = $('#package-price');
    const $calculatedSum = $('#package-calculated-sum');
    const $categoryId = $('#package-category');
    const $validityDays = $('#package-validity-days');
    const $isActive = $('#package-is-active');
    const $notes = $('#package-notes');
    const $itemsContainer = $('#package-items-container');

    const availableServices = vars('available_services') || [];
    let filterResults = [];
    let filterLimit = 20;

    /**
     * Add the page event listeners.
     */
    function addEventListeners() {
        /**
         * Event: Package Filter Form "Submit"
         */
        $filterPackages.on('submit', (event) => {
            event.preventDefault();
            const keyword = $filterPackages.find('.key').val().toLowerCase();
            App.Pages.Packages.filter(keyword);
        });

        /**
         * Event: Package Row "Click"
         */
        $filterPackages.on('click', '.package-row', (event) => {
            const packageId = $(event.currentTarget).attr('data-id');
            App.Pages.Packages.select(packageId, true);
            $packages.addClass('editing');
            $packages.find('.add-edit-delete-group').show();
            $packages.find('.save-cancel-group').hide();
            $packages.find('.record-details').find('input, select, textarea').prop('disabled', true);
            $packages.find('#add-package-item').prop('disabled', true);
            $('#edit-package, #delete-package').prop('disabled', false);
        });

        /**
         * Event: Add Package Button "Click"
         */
        $packages.on('click', '#add-package', () => {
            App.Pages.Packages.resetForm();
            $packages.find('.record-details').find('input, select, textarea').prop('disabled', false);
            $packages.find('.record-details .form-label span').prop('hidden', false);
            $packages.find('#add-package-item').prop('disabled', false);
            $packages.find('.add-edit-delete-group').hide();
            $packages.find('.save-cancel-group').show();
            $packages.addClass('editing');
            $isActive.prop('checked', true);
            renderItems([]);
            updateCalculatedSum();
        });

        /**
         * Event: Edit Package Button "Click"
         */
        $packages.on('click', '#edit-package', () => {
            $packages.find('.record-details').find('input, select, textarea').prop('disabled', false);
            $packages.find('.record-details .form-label span').prop('hidden', false);
            $packages.find('#add-package-item').prop('disabled', false);
            $packages.find('.add-edit-delete-group').hide();
            $packages.find('.save-cancel-group').show();
            $packages.addClass('editing');
        });

        /**
         * Event: Cancel Package Button "Click"
         */
        $packages.on('click', '#cancel-package', () => {
            const packageId = $id.val();
            App.Pages.Packages.resetForm();
            $packages.removeClass('editing');

            if (packageId) {
                App.Pages.Packages.select(packageId, true);
            }
        });

        /**
         * Event: Save Package Button "Click"
         */
        $packages.on('click', '#save-package', () => {
            const packageData = App.Pages.Packages.getFormData();

            if (!App.Pages.Packages.validate()) {
                return;
            }

            App.Pages.Packages.save(packageData);
        });

        /**
         * Event: Delete Package Button "Click"
         */
        $packages.on('click', '#delete-package', () => {
            const packageId = $id.val();

            const buttons = [
                {
                    text: lang('cancel'),
                    click: (event, messageModal) => {
                        messageModal.hide();
                    },
                },
                {
                    text: lang('delete'),
                    click: (event, messageModal) => {
                        App.Pages.Packages.remove(packageId);
                        messageModal.hide();
                    },
                },
            ];

            App.Utils.Message.show(lang('delete_package'), lang('delete_record_prompt'), buttons);
        });

        /**
         * Event: Add Item Button "Click"
         */
        $packages.on('click', '#add-package-item', () => {
            addItemRow();
            updateCalculatedSum();
        });

        /**
         * Event: Remove Item Button "Click"
         */
        $itemsContainer.on('click', '.remove-package-item', (event) => {
            $(event.currentTarget).closest('.package-item-row').remove();
            updateCalculatedSum();
        });

        /**
         * Event: Item Service or Quantity Change
         */
        $itemsContainer.on('change input', '.package-item-service, .package-item-quantity', () => {
            updateCalculatedSum();
        });
    }

    /**
     * Save package record to database.
     *
     * @param {Object} packageData
     */
    function save(packageData) {
        App.Http.Packages.save(packageData).then((response) => {
            App.Layouts.Backend.displayNotification(lang('package_saved'));
            App.Pages.Packages.resetForm();
            $packages.removeClass('editing');
            $filterPackages.find('.key').val('');
            App.Pages.Packages.filter('', response.id, true);
        });
    }

    /**
     * Delete a package record from database.
     *
     * @param {Number} id
     */
    function remove(id) {
        App.Http.Packages.destroy(id).then(() => {
            App.Layouts.Backend.displayNotification(lang('package_deleted'));
            App.Pages.Packages.resetForm();
            $packages.removeClass('editing');
            App.Pages.Packages.filter($filterPackages.find('.key').val());
        });
    }

    /**
     * Validate package form.
     *
     * @return {Boolean}
     */
    function validate() {
        $packages.find('.is-invalid').removeClass('is-invalid');
        $packages.find('.form-message').removeClass('alert-danger').hide();

        try {
            let missingRequired = false;

            $packages.find('.required').each((index, requiredField) => {
                if (!$(requiredField).val()) {
                    $(requiredField).addClass('is-invalid');
                    missingRequired = true;
                }
            });

            if (missingRequired) {
                throw new Error(lang('fields_are_required'));
            }

            if (Number($price.val()) < 0) {
                $price.addClass('is-invalid');
                throw new Error(lang('invalid_price'));
            }

            if (Number($validityDays.val()) < 1) {
                $validityDays.addClass('is-invalid');
                throw new Error(lang('invalid_validity_days'));
            }

            const items = collectItems();

            if (items.length === 0) {
                throw new Error(lang('package_requires_items'));
            }

            let invalidItem = false;

            items.forEach((item) => {
                if (!item.id_services || Number(item.quantity) < 1) {
                    invalidItem = true;
                }
            });

            if (invalidItem) {
                throw new Error(lang('package_invalid_items'));
            }

            return true;
        } catch (error) {
            $packages.find('.form-message').addClass('alert-danger').text(error.message).show();
            return false;
        }
    }

    /**
     * Reset the package form to its initial state.
     */
    function resetForm() {
        $filterPackages.find('.selected').removeClass('selected');
        $filterPackages.find('button').prop('disabled', false);
        $filterPackages.find('.results').css('color', '');

        $packages.find('.record-details').find('input, select, textarea').val('').prop('disabled', true);
        $packages.find('#add-package-item').prop('disabled', true);
        $packages.find('.record-details .form-label span').prop('hidden', true);
        $packages.find('.record-details #package-is-active').prop('checked', false);

        $packages.find('.add-edit-delete-group').show();
        $packages.find('.save-cancel-group').hide();
        $('#edit-package, #delete-package').prop('disabled', true);

        $packages.find('.record-details .is-invalid').removeClass('is-invalid');
        $packages.find('.record-details .form-message').hide();

        renderItems([]);
        updateCalculatedSum();
    }

    /**
     * Display a package record into the form.
     *
     * @param {Object} packageData
     */
    function display(packageData) {
        $id.val(packageData.id);
        $name.val(packageData.name);
        $price.val(Number(packageData.price).toFixed(2));
        $categoryId.val(packageData.id_service_categories !== null ? packageData.id_service_categories : '');
        $validityDays.val(packageData.validity_days);
        $isActive.prop('checked', Number(packageData.is_active) === 1);
        $notes.val(packageData.notes);

        renderItems(packageData.items || []);
        updateCalculatedSum();
    }

    /**
     * Filter package records.
     *
     * @param {String} keyword
     * @param {Number} selectId
     * @param {Boolean} show
     */
    function filter(keyword, selectId = null, show = false) {
        const categoryId = $filterPackages.find('.category-filter').val() ?? null;
        const isActiveRaw = $filterPackages.find('.active-filter').val();
        const isActive = isActiveRaw === '' || isActiveRaw === null || isActiveRaw === undefined ? null : isActiveRaw;

        App.Http.Packages.search(keyword, categoryId, isActive, filterLimit).then((response) => {
            filterResults = response;

            $filterPackages.find('.results').empty();

            response.forEach((packageData) => {
                $filterPackages.find('.results').append(App.Pages.Packages.getFilterHtml(packageData)).append($('<hr/>'));
            });

            if (response.length === 0) {
                $filterPackages.find('.results').append(
                    $('<em/>', {
                        text: lang('no_records_found'),
                    }),
                );
            } else if (response.length === filterLimit) {
                $('<button/>', {
                    type: 'button',
                    class: 'btn btn-outline-secondary w-100 load-more text-center',
                    text: lang('load_more'),
                    click: () => {
                        filterLimit += 20;
                        App.Pages.Packages.filter(keyword, selectId, show);
                    },
                }).appendTo('#filter-packages .results');
            }

            if (selectId) {
                App.Pages.Packages.select(selectId, show);
            }
        });
    }

    /**
     * Get the HTML for a package row in the filter results.
     *
     * @param {Object} packageData
     *
     * @return {Object}
     */
    function getFilterHtml(packageData) {
        const name = packageData.name;
        const currency = vars('currency') || '';
        const info = Number(packageData.price).toFixed(2) + (currency ? ' ' + currency : '');

        return $('<div/>', {
            class: 'package-row entry',
            'data-id': packageData.id,
            html: [
                $('<strong/>', {
                    text: name,
                }),
                $('<br/>'),
                $('<small/>', {
                    class: 'text-muted',
                    text: info,
                }),
                $('<br/>'),
            ],
        });
    }

    /**
     * Select a specific record from the current filter results.
     *
     * @param {Number} id
     * @param {Boolean} show
     */
    function select(id, show = false) {
        $filterPackages.find('.selected').removeClass('selected');

        $filterPackages.find('.package-row[data-id="' + id + '"]').addClass('selected');

        if (show) {
            const packageData = filterResults.find((filterResult) => Number(filterResult.id) === Number(id));

            App.Pages.Packages.display(packageData);

            $('#edit-package, #delete-package').prop('disabled', false);
        }
    }

    /**
     * Collect the package form data.
     *
     * @return {Object}
     */
    function getFormData() {
        return {
            id: $id.val() || null,
            name: $name.val(),
            price: $price.val(),
            id_service_categories: $categoryId.val() || null,
            validity_days: $validityDays.val(),
            is_active: $isActive.prop('checked') ? 1 : 0,
            notes: $notes.val(),
            items: collectItems(),
        };
    }

    /**
     * Collect dynamic service item rows.
     *
     * @return {Array}
     */
    function collectItems() {
        const items = [];

        $itemsContainer.find('.package-item-row').each((index, row) => {
            const $row = $(row);
            const serviceId = $row.find('.package-item-service').val();
            const quantity = $row.find('.package-item-quantity').val();

            if (!serviceId) {
                return;
            }

            items.push({
                id: $row.attr('data-id') || null,
                id_services: serviceId,
                quantity: quantity,
            });
        });

        return items;
    }

    /**
     * Render dynamic service item rows.
     *
     * @param {Array} items
     */
    function renderItems(items) {
        $itemsContainer.empty();

        if (items.length === 0) {
            addItemRow();
            return;
        }

        items.forEach((item) => {
            addItemRow(item);
        });
    }

    /**
     * Add a new service item row.
     *
     * @param {Object} item
     */
    function addItemRow(item = null) {
        const $row = $('<div/>', {
            class: 'row g-2 mb-2 package-item-row',
            'data-id': item && item.id ? item.id : '',
        });

        const $serviceCol = $('<div/>', {
            class: 'col-md-7',
        });

        const $select = $('<select/>', {
            class: 'form-select package-item-service',
        });

        $select.append(new Option('', ''));

        availableServices.forEach((service) => {
            const serviceCurrency = vars('currency') || '';
            const priceLabel = Number(service.price).toFixed(2) + (serviceCurrency ? ' ' + serviceCurrency : '');
            $select.append(new Option(service.name + ' (' + priceLabel + ')', service.id));
        });

        if (item && item.id_services) {
            $select.val(item.id_services);
        }

        $serviceCol.append($select);

        const $quantityCol = $('<div/>', {
            class: 'col-md-3',
        });

        $quantityCol.append(
            $('<input/>', {
                type: 'number',
                class: 'form-control package-item-quantity',
                min: '1',
                step: '1',
                value: item && item.quantity ? item.quantity : 1,
            }),
        );

        const $removeCol = $('<div/>', {
            class: 'col-md-2 d-flex align-items-start',
        });

        $removeCol.append(
            $('<button/>', {
                type: 'button',
                class: 'btn btn-outline-danger btn-sm remove-package-item w-100',
                html: $('<i/>', {
                    class: 'fas fa-trash',
                }),
            }),
        );

        $row.append($serviceCol, $quantityCol, $removeCol);
        $itemsContainer.append($row);
    }

    /**
     * Update the calculated reference sum based on selected services and quantities.
     */
    function updateCalculatedSum() {
        let sum = 0;

        $itemsContainer.find('.package-item-row').each((index, row) => {
            const $row = $(row);
            const serviceId = $row.find('.package-item-service').val();
            const quantity = Number($row.find('.package-item-quantity').val()) || 0;

            const service = availableServices.find((s) => String(s.id) === String(serviceId));

            if (service) {
                sum += Number(service.price) * quantity;
            }
        });

        $calculatedSum.val(sum.toFixed(2));
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        App.Pages.Packages.resetForm();
        App.Pages.Packages.filter('');
        App.Pages.Packages.addEventListeners();
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        filter,
        save,
        remove,
        validate,
        getFilterHtml,
        resetForm,
        display,
        select,
        getFormData,
        addEventListeners,
    };
})();
