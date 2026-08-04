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

    // NOTE: '0' is a valid VAT rate - do NOT use || / falsy checks here.
    const vatDefaultVar = vars('vat_default');
    const vatDefault = vatDefaultVar === undefined || vatDefaultVar === null || vatDefaultVar === '' ? '19' : vatDefaultVar;
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
     * Pre-fill the new-client form from a booking customer record.
     *
     * @param {Object} customer
     */
    function prefillFromCustomer(customer) {
        resetSelection();

        $clientSearch.val('');
        $clientSearchResults.empty();

        $modal.find('#client-type-pf').prop('checked', true);
        togglePjFields();

        $clientName.val(customer.name);
        $clientCui.val('');
        $clientRegCom.val('');
        $clientAddress.val(customer.address || '');
        $clientCity.val(customer.city || '');
        $clientCounty.val('');
        $clientEmail.val(customer.email || '');
        $clientPhone.val(customer.phone || '');

        $newClientForm.removeClass('d-none');

        showMessage(lang('prefill_from_bookings'));
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
                const label =
                    client.name +
                    (client.source === 'customer'
                        ? ' · ' + lang('from_bookings')
                        : client.cui
                          ? ' · CUI ' + client.cui
                          : '');

                $('<button/>', {
                    type: 'button',
                    class: 'list-group-item list-group-item-action client-result',
                    text: label,
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
     * Reset the invoice modal to a clean slate.
     *
     * Runs on EVERY modal open (hooked to show.bs.modal) and from the
     * "Emite altă factură" button. Generates a FRESH idempotency key so the
     * next emission is a genuinely new invoice server-side.
     */
    function resetModal() {
        idempotencyKey = generateIdempotencyKey();

        invoiceLines = [];
        renderLines();

        resetSelection();
        $clientSearch.val('');
        $clientSearchResults.empty();

        $newClientForm.addClass('d-none');
        $clientName.val('');
        $clientCui.val('');
        $clientRegCom.val('');
        $clientAddress.val('');
        $clientCity.val('');
        $clientCounty.val('');
        $clientEmail.val('');
        $clientPhone.val('');
        $modal.find('#client-type-pf').prop('checked', true);
        togglePjFields();

        $('#issue-message').addClass('d-none');
        $('#issue-invoice-submit').prop('disabled', false).removeClass('d-none');
        $('#issue-another').addClass('d-none');

        $('#invoice-issue-date').val(new Date().toISOString().slice(0, 10));
        $('#invoice-payment-method').val('cash');
        $('#invoice-is-draft').prop('checked', true);

        $('#invoice-currency').text(currency);

        loadCatalogs();
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

            modalInstance.show();
        });

        // Full reset on EVERY open (the bootstrap.Modal instance is reused).
        $modal.on('show.bs.modal', resetModal);

        /**
         * Event: "Emite altă factură" button "Click"
         */
        $modal.on('click', '#issue-another', resetModal);

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
                    const invoiceNumber = invoice.number
                        ? (invoice.series || '') + '-' + invoice.number
                        : lang('draft_without_number');

                    let successText = lang('invoice_issued') + ' ' + invoiceNumber;

                    if (invoice.payment_method === 'cash' && !Number(invoice.is_draft)) {
                        successText += ' · ' + lang('receipt_issued');
                    }

                    showIssueMessage(successText);

                    // Success state: offer "Emite altă factură" (full reset),
                    // keep the submit button hidden until then.
                    $button.addClass('d-none');
                    $modal.find('#issue-another').removeClass('d-none');

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
         * Event: History WhatsApp button "Click"
         */
        $('#invoices-history').on('click', '.wa-send', function () {
            const $button = $(this).prop('disabled', true);
            const originalHtml = $button.html();

            App.Http.Invoices.sendWhatsapp($button.data('invoice-id'))
                .done((response) => {
                    if (response.success) {
                        $button.html($('<i/>', {class: 'fas fa-check'}));

                        return;
                    }

                    const errorKeys = {
                        invalid_phone: 'no_phone',
                        no_phone: 'no_phone',
                        no_client: 'no_client',
                        invoice_not_issued: 'invoice_not_issued',
                    };

                    alert(lang(errorKeys[response.error] || 'whatsapp_send_failed') + (response.error && !errorKeys[response.error] ? ' ' + response.error : ''));

                    $button.prop('disabled', false).html(originalHtml);
                })
                .fail(() => {
                    alert(lang('whatsapp_send_failed'));

                    $button.prop('disabled', false).html(originalHtml);
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
            const client = $(this).data('client');

            if (client.source === 'customer') {
                prefillFromCustomer(client);
            } else {
                selectClient(client);
            }
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

                const number = invoice.number
                    ? (invoice.series || '') + '-' + invoice.number
                    : lang('draft_without_number');

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
                        class: 'btn btn-sm btn-outline-secondary me-1',
                        href: App.Utils.Url.siteUrl('invoices/pdf/' + invoice.id),
                        target: '_blank',
                        text: 'PDF',
                    }).appendTo($pdfCell);

                    $('<button/>', {
                        type: 'button',
                        class: 'btn btn-sm btn-outline-success wa-send',
                        // Inline WhatsApp glyph (the app only loads the FA solid
                        // font, no brands font - so fab fa-whatsapp cannot render).
                        html: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>',
                    })
                        .data('invoice-id', invoice.id)
                        .attr('data-tippy-content', lang('send_whatsapp'))
                        .appendTo($pdfCell);
                }
            });
        });
    }

    /**
     * Initialize the page module.
     */
    function initialize() {
        // Invoice emission is restricted server-side (admin + optional email
        // allowlist via INVOICE_ALLOWED_USERS). Users without the right see a
        // read-only invoice history view.
        if (!vars('can_issue_invoices')) {
            $('#issue-invoice').remove();
            $modal.find('#issue-invoice-submit, #issue-another, #save-client, #lookup-cui, #new-client-toggle').remove();
        }

        addEventListeners();

        loadHistory();
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        initialize,
    };
})();
