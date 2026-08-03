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
 * Invoices page client-side logic.
 *
 * Phase A: modal shell. Phase B: billing client — search existing, or create
 * new PF/PJ with ANAF CUI lookup prefill. Later phases add line items from
 * services/packages (C) and SmartBill emission + history (D).
 */
App.Pages.Invoices = (function () {
    const $page = $('#invoices-page');
    const $modal = $('#issue-invoice-modal');
    const $clientSearch = $('#client-search');
    const $clientSearchResults = $('#client-search-results');
    const $clientPicker = $('#client-picker');
    const $selectedClient = $('#selected-client');
    const $selectedClientText = $('#selected-client-text');
    const $newClientForm = $('#new-client-form');
    const $clientId = $('#client-id');
    const $clientCui = $('#client-cui');
    const $clientName = $('#client-name');
    const $clientRegCom = $('#client-reg-com');
    const $clientAddress = $('#client-address');
    const $clientCity = $('#client-city');
    const $clientCounty = $('#client-county');
    const $clientEmail = $('#client-email');
    const $clientPhone = $('#client-phone');
    const $message = $('#client-form-message');

    let modalInstance = null;
    let searchTimeout = null;

    // Phase C: invoice lines state (client-side only until Phase D).

    const catalogs = {
        service: null,
        package: null,
    };

    let invoiceLines = [];
    let lineSource = 'service';

    const vatDefault = vars('vat_default') || '19';
    const currency = vars('currency') || 'Lei';

    // Phase D: one idempotency key per modal open (double-submit protection).

    let idempotencyKey = null;

    /**
     * Show a message inside the modal.
     *
     * @param {String} text
     * @param {Boolean} isError
     */
    function showMessage(text, isError = false) {
        $message
            .removeClass('d-none alert-success alert-danger')
            .addClass(isError ? 'alert-danger' : 'alert-success')
            .text(text);
    }

    /**
     * Hide the modal message.
     */
    function hideMessage() {
        $message.addClass('d-none');
    }

    /**
     * Toggle the PJ-only fields based on the selected client type.
     */
    function togglePjFields() {
        const isPj = $modal.find('input[name="client-type"]:checked').val() === 'pj';

        $modal.find('.pj-only').toggleClass('d-none', !isPj);
    }

    /**
     * Reset the client selection and show the picker again.
     */
    function resetSelection() {
        $clientId.val('');
        $selectedClient.addClass('d-none');
        $clientPicker.removeClass('d-none');
        hideMessage();
    }

    /**
     * Mark a billing client as selected.
     *
     * @param {Object} client
     */
    function selectClient(client) {
        $clientId.val(client.id);
        $selectedClientText.text(client.name + (client.cui ? ' · CUI ' + client.cui : ''));
        $selectedClient.removeClass('d-none');
        $clientPicker.addClass('d-none');
        $newClientForm.addClass('d-none');
        $clientSearchResults.empty();
    }

    /**
     * Search billing clients and render the results list.
     *
     * @param {String} keyword
     */
    function searchClients(keyword) {
        App.Http.Invoices.searchClients(keyword).done((clients) => {
            $clientSearchResults.empty();

            clients.forEach((client) => {
                $('<button/>', {
                    type: 'button',
                    class: 'list-group-item list-group-item-action client-result',
                    text: client.name + (client.cui ? ' · CUI ' + client.cui : ''),
                })
                    .data('client', client)
                    .appendTo($clientSearchResults);
            });
        });
    }

    /**
     * Look up the CUI in ANAF and prefill the new-client form.
     */
    function lookupCui() {
        const cui = $clientCui.val().trim();

        if (!cui) {
            showMessage(lang('invalid_cui'), true);

            return;
        }

        const $button = $modal.find('#lookup-cui').prop('disabled', true);

        App.Http.Invoices.lookupCui(cui)
            .done((response) => {
                if (!response.success) {
                    const errorKeys = {
                        invalid_cui: 'invalid_cui',
                        not_found: 'anaf_not_found',
                        anaf_unavailable: 'anaf_unavailable',
                    };

                    showMessage(lang(errorKeys[response.error] || 'anaf_unavailable'), true);

                    return;
                }

                $clientName.val(response.client.name);
                $clientAddress.val(response.client.address);
                $clientCity.val(response.client.city);
                $clientCounty.val(response.client.county);
                $clientRegCom.val(response.client.reg_com);

                const vatInfo = response.client.vat_payer ? ' · ' + lang('vat_payer') : '';

                showMessage(lang('anaf_data_loaded') + vatInfo);
            })
            .always(() => {
                $button.prop('disabled', false);
            });
    }

    /**
     * Save the new billing client and select it.
     */
    function saveClient() {
        const clientData = {
            type: $modal.find('input[name="client-type"]:checked').val(),
            name: $clientName.val().trim(),
            cui: $clientCui.val().trim(),
            reg_com: $clientRegCom.val().trim(),
            address: $clientAddress.val().trim(),
            city: $clientCity.val().trim(),
            county: $clientCounty.val().trim(),
            email: $clientEmail.val().trim(),
            phone: $clientPhone.val().trim(),
        };

        App.Http.Invoices.saveClient(clientData)
            .done((response) => {
                selectClient(response.client);
                showMessage(lang('client_saved'));
            })
            .fail(() => {
                showMessage(lang('client_save_error'), true);
            });
    }

    /**
     * Load the services/packages catalogs (once) and fill the picker select.
     */
    function loadCatalogs() {
        if (catalogs.service === null) {
            App.Http.Invoices.listServices().done((services) => {
                catalogs.service = services;
                fillCatalogSelect();
            });
        }

        if (catalogs.package === null) {
            App.Http.Invoices.listPackages().done((packages) => {
                catalogs.package = packages;
            });
        }
    }

    /**
     * Fill the catalog select with the entries of the active source.
     */
    function fillCatalogSelect() {
        const $select = $('#line-catalog-select').empty();
        const entries = catalogs[lineSource] || [];

        entries.forEach((entry) => {
            $('<option/>', {
                value: entry.id,
                text: entry.name + ' · ' + Number(entry.price).toFixed(2) + ' ' + currency,
            })
                .data('entry', entry)
                .appendTo($select);
        });
    }

    /**
     * Switch the active line source tab.
     *
     * @param {String} source service|package|manual
     */
    function switchLineSource(source) {
        lineSource = source;

        $('#line-source-tabs .nav-link').removeClass('active');
        $(`#line-source-tabs .nav-link[data-source="${source}"]`).addClass('active');

        const isManual = source === 'manual';

        $('#line-picker-catalog').toggleClass('d-none', isManual);
        $('#line-picker-manual').toggleClass('d-none', !isManual);

        if (!isManual) {
            fillCatalogSelect();
        }
    }

    /**
     * Generate a fresh idempotency key (one per modal open).
     *
     * @returns {String}
     */
    function generateIdempotencyKey() {
        if (window.crypto && window.crypto.randomUUID) {
            return window.crypto.randomUUID();
        }

        return 'inv-' + Date.now() + '-' + Math.random().toString(36).substring(2, 12);
    }

    /**
     * Add a new invoice line and re-render.
     *
     * @param {String} description
     * @param {Number} price
     * @param {String} sourceType service|package|product|manual
     * @param {Number|null} sourceId
     */
    function addLine(description, price, sourceType = 'manual', sourceId = null) {
        description = (description || '').trim();
        price = Number(price);

        if (!description || !(price >= 0)) {
            return;
        }

        invoiceLines.push({
            description,
            qty: 1,
            price,
            vat_rate: Number(vatDefault),
            source_type: sourceType,
            source_id: sourceId,
        });

        renderLines();
    }

    /**
     * Re-render the invoice lines table and the totals.
     */
    function renderLines() {
        const $tbody = $('#invoice-lines').empty();

        invoiceLines.forEach((line, index) => {
            const $row = $('<tr/>').appendTo($tbody);

            $('<td/>', {text: line.description}).appendTo($row);

            $('<td/>').append(
                $('<input/>', {
                    class: 'form-control form-control-sm line-qty',
                    type: 'number',
                    min: '1',
                    step: '1',
                    value: line.qty,
                }).data('index', index),
            ).appendTo($row);

            $('<td/>').append(
                $('<input/>', {
                    class: 'form-control form-control-sm line-price',
                    type: 'number',
                    min: '0',
                    step: '0.01',
                    value: line.price,
                }).data('index', index),
            ).appendTo($row);

            $('<td/>').append(
                $('<input/>', {
                    class: 'form-control form-control-sm line-vat-rate',
                    type: 'number',
                    min: '0',
                    step: '1',
                    value: line.vat_rate,
                }).data('index', index),
            ).appendTo($row);

            $('<td/>', {
                class: 'line-total',
                text: (line.qty * line.price).toFixed(2),
            }).appendTo($row);

            $('<td/>').append(
                $('<button/>', {
                    type: 'button',
                    class: 'btn btn-sm btn-outline-danger line-remove',
                    html: $('<i/>', {class: 'fas fa-trash-alt'}),
                }).data('index', index),
            ).appendTo($row);
        });

        updateTotals();
    }

    /**
     * Recompute subtotal, VAT and grand total from the current lines.
     */
    function updateTotals() {
        let subtotal = 0;
        let vat = 0;

        invoiceLines.forEach((line) => {
            const lineTotal = line.qty * line.price;

            subtotal += lineTotal;
            vat += (lineTotal * line.vat_rate) / 100;
        });

        $('#invoice-subtotal').text(subtotal.toFixed(2));
        $('#invoice-vat').text(vat.toFixed(2));
        $('#invoice-total').text((subtotal + vat).toFixed(2));
    }

    /**
     * Add the page event listeners.
     */
    function addEventListeners() {
        /**
         * Event: "Emite factură" button "Click"
         */
        $page.on('click', '#issue-invoice', () => {
            if (!modalInstance) {
                modalInstance = new bootstrap.Modal($modal[0]);
            }

            $('#invoice-currency').text(currency);

            idempotencyKey = generateIdempotencyKey();

            $('#invoice-issue-date').val(new Date().toISOString().slice(0, 10));

            loadCatalogs();

            modalInstance.show();
        });

        /**
         * Event: Line source tab "Click"
         */
        $('#line-source-tabs').on('click', '.nav-link:not(.disabled)', function () {
            switchLineSource($(this).data('source'));
        });

        /**
         * Event: "Add catalog line" button "Click"
         */
        $modal.on('click', '#add-catalog-line', () => {
            const entry = $('#line-catalog-select option:selected').data('entry');

            if (entry) {
                addLine(entry.name, entry.price, lineSource, entry.id);
            }
        });

        /**
         * Event: "Add manual line" button "Click"
         */
        $modal.on('click', '#add-manual-line', () => {
            addLine($('#manual-line-description').val(), $('#manual-line-price').val(), 'manual', null);

            $('#manual-line-description').val('');
            $('#manual-line-price').val('');
        });

        /**
         * Event: Line input "Input" (qty / price / vat rate)
         */
        $('#invoice-lines').on('input', '.line-qty, .line-price, .line-vat-rate', function () {
            const index = $(this).data('index');
            const value = Number($(this).val());

            if ($(this).hasClass('line-qty')) {
                invoiceLines[index].qty = value >= 1 ? value : 1;
            } else if ($(this).hasClass('line-price')) {
                invoiceLines[index].price = value >= 0 ? value : 0;
            } else {
                invoiceLines[index].vat_rate = value >= 0 ? value : 0;
            }

            $(this).closest('tr').find('.line-total').text((invoiceLines[index].qty * invoiceLines[index].price).toFixed(2));

            updateTotals();
        });

        /**
         * Event: "Generează factura" button "Click" (double-submit guarded)
         */
        $modal.on('click', '#issue-invoice-submit', function () {
            const $button = $(this);
            const $issueMessage = $('#issue-message');

            const showIssueMessage = (text, isError) => {
                $issueMessage
                    .removeClass('d-none alert-success alert-danger')
                    .addClass(isError ? 'alert-danger' : 'alert-success')
                    .text(text);
            };

            if (!$('#client-id').val()) {
                showIssueMessage(lang('select_client_first'), true);

                return;
            }

            if (!invoiceLines.length) {
                showIssueMessage(lang('add_line_first'), true);

                return;
            }

            if (!$('#invoice-issue-date').val()) {
                showIssueMessage(lang('select_issue_date'), true);

                return;
            }

            // Double-submit guard: disable immediately, re-enable on failure only.

            $button.prop('disabled', true);

            const payload = {
                billing_client_id: $('#client-id').val(),
                issue_date: $('#invoice-issue-date').val(),
                payment_method: $('#invoice-payment-method').val(),
                is_draft: $('#invoice-is-draft').is(':checked') ? 1 : 0,
                idempotency_key: idempotencyKey,
                lines: invoiceLines.map((line) => ({
                    source_type: line.source_type,
                    source_id: line.source_id,
                    description: line.description,
                    qty: line.qty,
                    unit_price: line.price,
                    vat_rate: line.vat_rate,
                })),
            };

            App.Http.Invoices.issue(payload)
                .done((response) => {
                    if (!response.success) {
                        showIssueMessage(
                            response.message ? lang('invoice_issue_failed') + ' ' + response.message : lang(response.error || 'invoice_issue_failed'),
                            true,
                        );

                        $button.prop('disabled', false);

                        return;
                    }

                    const invoice = response.invoice;
                    const invoiceNumber = (invoice.series || '') + (invoice.number || '');

                    showIssueMessage(lang('invoice_issued') + ' ' + invoiceNumber);

                    invoiceLines = [];
                    renderLines();
                    loadHistory();
                })
                .fail(() => {
                    showIssueMessage(lang('invoice_issue_failed'), true);

                    $button.prop('disabled', false);
                });
        });

        /**
         * Event: Line remove button "Click"
         */
        $('#invoice-lines').on('click', '.line-remove', function () {
            invoiceLines.splice($(this).data('index'), 1);

            renderLines();
        });


        /**
         * Event: Client search "Input" (debounced)
         */
        $clientSearch.on('input', () => {
            clearTimeout(searchTimeout);

            const keyword = $clientSearch.val().trim();

            if (keyword.length < 2) {
                $clientSearchResults.empty();

                return;
            }

            searchTimeout = setTimeout(() => searchClients(keyword), 300);
        });

        /**
         * Event: Client search result "Click"
         */
        $clientSearchResults.on('click', '.client-result', function () {
            selectClient($(this).data('client'));
        });

        /**
         * Event: "Client nou" / "Schimbă clientul" buttons "Click"
         */
        $modal.on('click', '#new-client-toggle', () => {
            $newClientForm.removeClass('d-none');
            togglePjFields();
        });

        $modal.on('click', '#change-client', () => {
            resetSelection();
            $clientSearch.val('').trigger('focus');
        });

        /**
         * Event: Client type radio "Change"
         */
        $modal.on('change', 'input[name="client-type"]', togglePjFields);

        /**
         * Event: "Caută în ANAF" button "Click"
         */
        $modal.on('click', '#lookup-cui', lookupCui);

        /**
         * Event: "Salvează client" button "Click"
         */
        $modal.on('click', '#save-client', saveClient);
    }

    /**
     * Load the invoice history table.
     */
    function loadHistory() {
        const statusLabels = {
            pending: lang('pending'),
            issued: lang('issued'),
            failed: lang('failed'),
        };

        App.Http.Invoices.history().done((invoices) => {
            const $tbody = $('#invoices-history').empty();

            if (!invoices.length) {
                $('<tr/>').append(
                    $('<td/>', {colspan: 6, class: 'text-muted', text: lang('no_invoices')}),
                ).appendTo($tbody);

                return;
            }

            invoices.forEach((invoice) => {
                const $row = $('<tr/>').appendTo($tbody);

                $('<td/>', {text: invoice.issue_date || invoice.created_at}).appendTo($row);
                $('<td/>', {text: invoice.client_name || '-'}).appendTo($row);

                const number = invoice.series && invoice.number ? invoice.series + invoice.number : '-';

                $('<td/>', {text: number}).appendTo($row);
                $('<td/>', {text: Number(invoice.total).toFixed(2)}).appendTo($row);

                let statusText = statusLabels[invoice.smartbill_status] || invoice.smartbill_status;

                if (Number(invoice.is_draft)) {
                    statusText += ' (' + lang('draft') + ')';
                }

                $('<td/>', {text: statusText}).appendTo($row);

                const $pdfCell = $('<td/>').appendTo($row);

                if (invoice.smartbill_status === 'issued' && invoice.series && invoice.number) {
                    $('<a/>', {
                        class: 'btn btn-sm btn-outline-secondary',
                        href: App.Utils.Url.siteUrl('invoices/pdf/' + invoice.id),
                        target: '_blank',
                        text: 'PDF',
                    }).appendTo($pdfCell);
                }
            });
        });
    }

    /**
     * Initialize the page module.
     */
    function initialize() {
        addEventListeners();

        loadHistory();
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        initialize,
    };
})();
