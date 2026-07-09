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
 * Customer packages page client-side logic.
 *
 * Handles listing, filtering, selling and manual adjustment of subscription
 * packages sold to customers.
 */
App.Pages.CustomerPackages = (function () {
    const $page = $('#customer-packages-page');
    const $filter = $('#filter-customer-packages');
    const $details = $page.find('.record-details');

    const $id = $('#customer-package-id');
    const $customer = $('#customer-package-customer');
    const $package = $('#customer-package-package');
    const $purchaseDate = $('#customer-package-purchase-date');
    const $expiryDate = $('#customer-package-expiry-date');
    const $status = $('#customer-package-status');
    const $notes = $('#customer-package-notes');
    const $itemsContainer = $('#customer-package-items-container');

    const $sellModal = $('#sell-customer-package-modal');
    const $sellCustomerKeyword = $('#sell-customer-keyword');
    const $sellCustomerResults = $('#sell-customer-results');
    const $sellCustomerId = $('#sell-customer-id');
    const $sellPackageId = $('#sell-package-id');
    const $sellNotes = $('#sell-notes');

    let filterResults = [];
    let filterLimit = 20;
    let customerSearchTimeout = null;
    let sellModalInstance = null;

    /**
     * Add the page event listeners.
     */
    function addEventListeners() {
        $filter.on('submit', (event) => {
            event.preventDefault();
            const keyword = $filter.find('.key').val().toLowerCase();
            App.Pages.CustomerPackages.filter(keyword);
        });

        $filter.on('click', '.customer-package-row', (event) => {
            const customerPackageId = $(event.currentTarget).attr('data-id');
            App.Pages.CustomerPackages.select(customerPackageId, true);
            $page.addClass('editing');
            $details.find('.add-edit-delete-group').show();
            $details.find('.save-cancel-group').hide();
            $details.find('input, select, textarea').not('#customer-package-id').prop('disabled', true);
            $itemsContainer.find('input').prop('disabled', true);
            $('#edit-customer-package, #delete-customer-package').prop('disabled', false);
        });

        $page.on('click', '#sell-customer-package', () => {
            App.Pages.CustomerPackages.resetSaleModal();
            sellModalInstance.show();
        });

        $sellCustomerKeyword.on('keyup', () => {
            clearTimeout(customerSearchTimeout);
            customerSearchTimeout = setTimeout(() => {
                App.Pages.CustomerPackages.searchCustomers();
            }, 400);
        });

        $sellCustomerResults.on('click', '.list-group-item', (event) => {
            const $target = $(event.currentTarget);
            const customerId = $target.attr('data-id');
            const customerName = $target.find('strong').text();
            $sellCustomerId.val(customerId);
            $sellCustomerKeyword.val(customerName);
            $sellCustomerResults.empty();
        });

        $(document).on('click', '#confirm-sell-customer-package', () => {
            App.Pages.CustomerPackages.sell();
        });

        $page.on('click', '#edit-customer-package', () => {
            $details.find('input, select, textarea').not('#customer-package-id, #customer-package-customer, #customer-package-package, #customer-package-purchase-date, #customer-package-expiry-date, #customer-package-status').prop('disabled', false);
            $itemsContainer.find('input').prop('disabled', false);
            $details.find('.add-edit-delete-group').hide();
            $details.find('.save-cancel-group').show();
            $page.addClass('editing');
        });

        $page.on('click', '#cancel-customer-package', () => {
            const customerPackageId = $id.val();
            App.Pages.CustomerPackages.resetForm();
            $page.removeClass('editing');

            if (customerPackageId) {
                App.Pages.CustomerPackages.select(customerPackageId, true);
            }
        });

        $page.on('click', '#save-customer-package', () => {
            let items = [];

            try {
                items = App.Pages.CustomerPackages.collectItems();
            } catch (error) {
                $details.find('.form-message').addClass('alert-danger').text(error.message).show();
                return;
            }

            const customerPackageId = $id.val();

            if (!customerPackageId) {
                return;
            }

            App.Http.CustomerPackages.update(customerPackageId, items).then((response) => {
                App.Layouts.Backend.displayNotification(lang('customer_package_saved'));
                App.Pages.CustomerPackages.resetForm();
                $page.removeClass('editing');
                $filter.find('.key').val('');
                App.Pages.CustomerPackages.filter('', response.id, true);
            }).catch((response) => {
                const message = response?.responseJSON?.message || lang('unexpected_issues_occurred');
                $details.find('.form-message').addClass('alert-danger').text(message).show();
            });
        });

        $page.on('click', '#delete-customer-package', () => {
            const customerPackageId = $id.val();

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
                        App.Pages.CustomerPackages.remove(customerPackageId);
                        messageModal.hide();
                    },
                },
            ];

            App.Utils.Message.show(lang('delete_customer_package'), lang('delete_record_prompt'), buttons);
        });
    }

    /**
     * Search customers for the sale modal.
     */
    function searchCustomers() {
        const keyword = $sellCustomerKeyword.val().trim();

        if (!keyword) {
            $sellCustomerResults.empty();
            return;
        }

        App.Http.Customers.search(keyword, 20).then((customers) => {
            $sellCustomerResults.empty();

            if (customers.length === 0) {
                $sellCustomerResults.append(
                    $('<div/>', {
                        class: 'list-group-item text-muted',
                        text: lang('no_records_found'),
                    }),
                );
                return;
            }

            customers.forEach((customer) => {
                const name = `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || lang('no_name');
                const info = [customer.email, customer.phone_number].filter(Boolean).join(', ');

                $sellCustomerResults.append(
                    $('<button/>', {
                        type: 'button',
                        class: 'list-group-item list-group-item-action',
                        'data-id': customer.id,
                        html: `<strong>${name}</strong><br><small class="text-muted">${info}</small>`,
                    }),
                );
            });
        });
    }

    /**
     * Sell a package to the selected customer.
     */
    function sell() {
        $sellModal.find('.form-message').removeClass('alert-danger').hide();

        const customerId = $sellCustomerId.val();
        const packageId = $sellPackageId.val();
        const notes = $sellNotes.val();

        if (!customerId) {
            showSaleError(lang('select_customer'));
            return;
        }

        if (!packageId) {
            showSaleError(lang('select_package'));
            return;
        }

        App.Http.CustomerPackages.store(customerId, packageId, notes).then((response) => {
            App.Layouts.Backend.displayNotification(lang('customer_package_saved'));
            sellModalInstance.hide();
            App.Pages.CustomerPackages.resetForm();
            $filter.find('.key').val('');
            App.Pages.CustomerPackages.filter('', response.id, true);
        }).catch((response) => {
            const message = response?.responseJSON?.message || lang('unexpected_issues_occurred');
            showSaleError(message);
        });
    }

    /**
     * Show an error in the sale modal.
     *
     * @param {String} message
     */
    function showSaleError(message) {
        $sellModal.find('.form-message').addClass('alert-danger').text(message).show();
    }

    /**
     * Reset the sale modal form.
     */
    function resetSaleModal() {
        $sellCustomerKeyword.val('');
        $sellCustomerResults.empty();
        $sellCustomerId.val('');
        $sellPackageId.val('');
        $sellNotes.val('');
        $sellModal.find('.form-message').hide();
    }

    /**
     * Delete a customer package.
     *
     * @param {Number} id
     */
    function remove(id) {
        App.Http.CustomerPackages.destroy(id).then(() => {
            App.Layouts.Backend.displayNotification(lang('customer_package_deleted'));
            App.Pages.CustomerPackages.resetForm();
            $page.removeClass('editing');
            App.Pages.CustomerPackages.filter($filter.find('.key').val());
        });
    }

    /**
     * Reset the details form to its initial state.
     */
    function resetForm() {
        $filter.find('.selected').removeClass('selected');
        $filter.find('button').prop('disabled', false);
        $filter.find('.results').css('color', '');

        $details.find('input, select, textarea').val('').prop('disabled', true);
        $details.find('#customer-package-status').prop('checked', false);

        $details.find('.add-edit-delete-group').show();
        $details.find('.save-cancel-group').hide();
        $('#edit-customer-package, #delete-customer-package').prop('disabled', true);

        $details.find('.is-invalid').removeClass('is-invalid');
        $details.find('.form-message').hide();

        $itemsContainer.empty();
    }

    /**
     * Display a customer package into the details panel.
     *
     * @param {Object} customerPackage
     */
    function display(customerPackage) {
        $id.val(customerPackage.id);

        const customerName = `${customerPackage.customer_first_name || ''} ${customerPackage.customer_last_name || ''}`.trim();
        $customer.val(customerName);
        $package.val(customerPackage.package_name);
        $purchaseDate.val(formatDateTime(customerPackage.purchase_date));
        $expiryDate.val(customerPackage.expiry_date ? formatDateTime(customerPackage.expiry_date) : '-');
        $status.val(customerPackage.is_active ? lang('active') : lang('inactive'));
        $notes.val(customerPackage.notes);

        renderItems(customerPackage.items || []);
    }

    /**
     * Filter customer package records.
     *
     * @param {String} keyword
     * @param {Number} selectId
     * @param {Boolean} show
     */
    function filter(keyword, selectId = null, show = false) {
        const isActiveRaw = $filter.find('.active-filter').val();
        const isActive = isActiveRaw === '' || isActiveRaw === null || isActiveRaw === undefined ? null : isActiveRaw;

        App.Http.CustomerPackages.search(keyword, isActive, filterLimit).then((response) => {
            filterResults = response;

            const $tableBody = $('#customer-packages-table tbody');
            $tableBody.empty();

            response.forEach((customerPackage) => {
                $tableBody.append(App.Pages.CustomerPackages.getFilterHtml(customerPackage));
            });

            if (response.length === 0) {
                $tableBody.append(
                    $('<tr/>').append(
                        $('<td/>', {
                            colspan: '7',
                            class: 'text-muted text-center',
                            text: lang('no_records_found'),
                        }),
                    ),
                );
            } else if (response.length === filterLimit) {
                $('<button/>', {
                    type: 'button',
                    class: 'btn btn-outline-secondary w-100 load-more text-center',
                    text: lang('load_more'),
                    click: () => {
                        filterLimit += 20;
                        App.Pages.CustomerPackages.filter(keyword, selectId, show);
                    },
                }).appendTo('#filter-customer-packages .results');
            }

            if (selectId) {
                App.Pages.CustomerPackages.select(selectId, show);
            }
        });
    }

    /**
     * Get the HTML for a customer package row in the filter results.
     *
     * @param {Object} customerPackage
     *
     * @return {Object}
     */
    function getFilterHtml(customerPackage) {
        const customerName = `${customerPackage.customer_first_name || ''} ${customerPackage.customer_last_name || ''}`.trim() || lang('no_name');
        const statusBadgeClass = customerPackage.is_active ? 'bg-success' : 'bg-secondary';
        const statusText = customerPackage.is_active ? lang('active') : lang('inactive');
        const totalRemaining = Number(customerPackage.total_remaining ?? 0);
        const totalQuantity = Number(customerPackage.total_quantity ?? 0);
        const sessionsText = `${totalRemaining}/${totalQuantity}`;
        const expiryText = customerPackage.expiry_date ? formatDate(customerPackage.expiry_date) : '—';

        return $('<tr/>', {
            class: 'customer-package-row entry',
            'data-id': customerPackage.id,
            html: [
                $('<td/>', { text: customerName }),
                $('<td/>', { text: customerPackage.package_name }),
                $('<td/>', { class: 'text-center', text: sessionsText }),
                $('<td/>', { text: formatDate(customerPackage.purchase_date) }),
                $('<td/>', { text: expiryText }),
                $('<td/>').append(
                    $('<span/>', {
                        class: `badge ${statusBadgeClass}`,
                        text: statusText,
                    }),
                ),
                $('<td/>', { class: 'text-end' }).append(
                    $('<button/>', {
                        type: 'button',
                        class: 'btn btn-sm btn-outline-secondary view-customer-package',
                        html: `<i class="fas fa-eye me-1"></i> ${lang('details')}`,
                    }),
                ),
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
        $filter.find('.selected').removeClass('selected');

        $filter.find('.customer-package-row[data-id="' + id + '"]').addClass('selected');

        if (show) {
            const customerPackage = filterResults.find((filterResult) => Number(filterResult.id) === Number(id));

            if (customerPackage) {
                App.Pages.CustomerPackages.display(customerPackage);
                $('#edit-customer-package, #delete-customer-package').prop('disabled', false);
            }
        }
    }

    /**
     * Render the items table.
     *
     * @param {Array} items
     */
    function renderItems(items) {
        $itemsContainer.empty();

        if (items.length === 0) {
            $itemsContainer.append(
                $('<em/>', {
                    class: 'text-muted',
                    text: lang('no_records_found'),
                }),
            );
            return;
        }

        const $table = $('<table/>', {
            class: 'table table-sm table-borderless mb-0',
        });

        const $thead = $('<thead/>').append(
            $('<tr/>').append(
                $('<th/>', { text: lang('service') }),
                $('<th/>', { text: lang('total') }),
                $('<th/>', { text: lang('remaining') }),
                $('<th/>', { text: lang('reason'), class: 'adjustment-reason-column' }),
            ),
        );

        const $tbody = $('<tbody/>');

        items.forEach((item) => {
            const $row = $('<tr/>', {
                class: 'customer-package-item-row',
                'data-id': item.id,
            });

            $row.append($('<td/>', { text: item.service_name }));
            $row.append($('<td/>', { text: item.quantity_total }));

            const $remainingInput = $('<input/>', {
                type: 'number',
                class: 'form-control form-control-sm customer-package-item-remaining',
                min: '0',
                step: '1',
                value: item.quantity_remaining,
                disabled: true,
                'data-original': item.quantity_remaining,
            });

            $row.append($('<td/>').append($remainingInput));

            const $reasonInput = $('<input/>', {
                type: 'text',
                class: 'form-control form-control-sm customer-package-item-reason',
                placeholder: lang('reason'),
                disabled: true,
            });

            $row.append($('<td/>', { class: 'adjustment-reason-column' }).append($reasonInput));

            $tbody.append($row);
        });

        $table.append($thead, $tbody);
        $itemsContainer.append($table);
    }

    /**
     * Collect item adjustment data from the form.
     *
     * @return {Array}
     */
    function collectItems() {
        const items = [];

        $itemsContainer.find('.customer-package-item-row').each((index, row) => {
            const $row = $(row);
            const itemId = $row.attr('data-id');
            const originalRemaining = Number($row.find('.customer-package-item-remaining').attr('data-original'));
            const newRemaining = Number($row.find('.customer-package-item-remaining').val());
            const reason = $row.find('.customer-package-item-reason').val();

            if (isNaN(newRemaining) || newRemaining < 0) {
                throw new Error(lang('invalid_remaining_quantity'));
            }

            if (newRemaining !== originalRemaining) {
                items.push({
                    id: itemId,
                    quantity_remaining: newRemaining,
                    reason: reason || null,
                });
            }
        });

        return items;
    }

    /**
     * Format a date string for display (dd/mm/yyyy).
     *
     * @param {String} value
     *
     * @return {String}
     */
    function formatDate(value) {
        if (!value) {
            return '';
        }

        const date = new Date(value.replace(/ /, 'T'));

        if (isNaN(date.getTime())) {
            return value;
        }

        const pad = (number) => String(number).padStart(2, '0');

        return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
    }

    /**
     * Format a datetime string for display.
     *
     * @param {String} value
     *
     * @return {String}
     */
    function formatDateTime(value) {
        if (!value) {
            return '';
        }

        const date = new Date(value.replace(/ /, 'T'));

        if (isNaN(date.getTime())) {
            return value;
        }

        const pad = (number) => String(number).padStart(2, '0');

        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        sellModalInstance = new bootstrap.Modal($sellModal[0]);

        App.Pages.CustomerPackages.resetForm();
        App.Pages.CustomerPackages.filter('');
        App.Pages.CustomerPackages.addEventListeners();
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        filter,
        select,
        display,
        resetForm,
        getFilterHtml,
        addEventListeners,
        searchCustomers,
        sell,
        resetSaleModal,
        remove,
        collectItems,
    };
})();
