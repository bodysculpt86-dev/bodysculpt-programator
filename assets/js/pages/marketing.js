/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * WhatsApp marketing broadcast page (Flaxxa WAPI).
 * ---------------------------------------------------------------------------- */

/**
 * Marketing page.
 *
 * Sends the approved WhatsApp marketing template to all customers
 * with a valid phone number, in small batches with live progress.
 */
App.Pages.Marketing = (function () {
    const $procedure = $('#campaign-procedure');
    const $discount = $('#campaign-discount');
    const $validUntil = $('#campaign-valid-until');
    const $recipientsCount = $('#recipients-count');
    const $testPhone = $('#test-phone');
    const $testButton = $('#test-button');
    const $testResult = $('#test-result');
    const $sendButton = $('#send-button');
    const $progressSection = $('#send-progress-section');
    const $progressBar = $('#send-progress-bar');
    const $progressText = $('#send-progress-text');
    const $resultsBody = $('#send-results-body');

    /**
     * Initialize the page.
     */
    function init() {
        loadRecipientsCount();

        $procedure.on('input', updatePreview);
        $discount.on('input', updatePreview);
        $validUntil.on('input', updatePreview);

        $testButton.on('click', onTestClick);
        $sendButton.on('click', onSendClick);
    }

    /**
     * Read and validate the campaign fields.
     *
     * @return {Object|null} Campaign data or null when invalid.
     */
    function getCampaignData() {
        const procedure = $procedure.val().trim();
        const discount = $discount.val().trim();
        const validUntil = $validUntil.val().trim();

        if (!procedure || !discount || !validUntil) {
            App.Utils.Message.show(lang('marketing_fill_all_fields'));
            return null;
        }

        return { procedure, discount, validUntil };
    }

    /**
     * Send a single test message to the entered phone number.
     */
    function onTestClick() {
        const campaign = getCampaignData();

        if (!campaign) {
            return;
        }

        const phone = $testPhone.val().trim();

        if (!phone) {
            showTestResult(false, lang('marketing_test_phone_required'));
            return;
        }

        $testButton.prop('disabled', true);
        $testResult.addClass('d-none');

        const url = App.Utils.Url.siteUrl('marketing/send_test');

        $.post(url, {
            csrf_token: vars('csrf_token'),
            phone: phone,
            procedure: campaign.procedure,
            discount: campaign.discount,
            valid_until: campaign.validUntil,
        })
            .done((response) => {
                if (response.success) {
                    showTestResult(true, lang('marketing_test_sent'));
                } else {
                    showTestResult(false, response.error === 'invalid_phone' ? lang('marketing_test_invalid_phone') : (response.error || lang('marketing_failed')));
                }
            })
            .fail(() => {
                showTestResult(false, lang('marketing_send_error'));
            })
            .always(() => {
                $testButton.prop('disabled', false);
            });
    }

    /**
     * Display the outcome of the test send next to the test button.
     *
     * @param {boolean} success
     * @param {string} message
     */
    function showTestResult(success, message) {
        $testResult
            .removeClass('d-none text-success text-danger')
            .addClass(success ? 'text-success' : 'text-danger')
            .text(message);
    }

    /**
     * Fetch and display the number of customers with a valid phone.
     */
    function loadRecipientsCount() {
        const url = App.Utils.Url.siteUrl('marketing/recipients_count');

        $.post(url, {
            csrf_token: vars('csrf_token'),
        })
            .done((response) => {
                $recipientsCount.text(response.total);
            })
            .fail(() => {
                $recipientsCount.text('?');
            });
    }

    /**
     * Update the WhatsApp-style live preview.
     */
    function updatePreview() {
        setPreviewValue('#preview-procedure', $procedure.val(), lang('campaign_procedure'));
        setPreviewValue('#preview-discount', $discount.val(), '0');
        setPreviewValue('#preview-valid-until', $validUntil.val(), lang('campaign_valid_until'));
    }

    /**
     * Set a preview span value, toggling the placeholder styling.
     *
     * @param {string} selector
     * @param {string} value
     * @param {string} placeholder
     */
    function setPreviewValue(selector, value, placeholder) {
        const $element = $(selector);
        const hasValue = value && value.trim() !== '';

        $element.text(hasValue ? value : placeholder);
        $element.toggleClass('wa-placeholder', !hasValue);
    }

    /**
     * Validate the form and start the batch sending loop.
     */
    function onSendClick() {
        const campaign = getCampaignData();

        if (!campaign) {
            return;
        }

        if (!confirm(lang('marketing_confirm_send'))) {
            return;
        }

        setFormEnabled(false);

        $progressSection.removeClass('d-none');
        $progressBar.css('width', '0%').removeClass('bg-danger').addClass('bg-success progress-bar-animated');
        $progressText.text('');
        $resultsBody.empty();

        sendBatch(0, campaign.procedure, campaign.discount, campaign.validUntil);
    }

    /**
     * Send one batch, then continue with the next offset until done.
     *
     * @param {number} offset
     * @param {string} procedure
     * @param {string} discount
     * @param {string} validUntil
     */
    function sendBatch(offset, procedure, discount, validUntil) {
        const url = App.Utils.Url.siteUrl('marketing/send_batch');

        $.post(url, {
            csrf_token: vars('csrf_token'),
            offset: offset,
            procedure: procedure,
            discount: discount,
            valid_until: validUntil,
        })
            .done((response) => {
                appendResults(response.results || []);
                updateProgress(response.processed, response.total);

                if (response.done) {
                    onSendingFinished();
                } else {
                    sendBatch(response.processed, procedure, discount, validUntil);
                }
            })
            .fail(() => {
                $progressBar.removeClass('bg-success progress-bar-animated').addClass('bg-danger');
                App.Utils.Message.show(lang('marketing_send_error'));
                setFormEnabled(true);
            });
    }

    /**
     * Append per-recipient results to the results table.
     *
     * @param {Array} results
     */
    function appendResults(results) {
        results.forEach((result) => {
            const status = result.success
                ? `<span class="text-success"><i class="fas fa-check me-1"></i>${lang('marketing_sent')}</span>`
                : `<span class="text-danger"><i class="fas fa-times me-1"></i>${escapeHtml(result.error || lang('marketing_failed'))}</span>`;

            $resultsBody.append(`
                <tr>
                    <td>${escapeHtml(result.name)}</td>
                    <td>${escapeHtml(result.phone)}</td>
                    <td>${status}</td>
                </tr>
            `);
        });
    }

    /**
     * Update the progress bar and counter text.
     *
     * @param {number} processed
     * @param {number} total
     */
    function updateProgress(processed, total) {
        const percent = total > 0 ? Math.round((processed / total) * 100) : 100;

        $progressBar.css('width', percent + '%');
        $progressText.text(`${processed} / ${total}`);
    }

    /**
     * Handle the completion of all batches.
     */
    function onSendingFinished() {
        $progressBar.removeClass('progress-bar-animated');
        $progressText.text($progressText.text() + ' — ' + lang('marketing_campaign_finished'));
        setFormEnabled(true);
    }

    /**
     * Enable or disable the campaign form while sending.
     *
     * @param {boolean} enabled
     */
    function setFormEnabled(enabled) {
        $procedure.prop('disabled', !enabled);
        $discount.prop('disabled', !enabled);
        $validUntil.prop('disabled', !enabled);
        $testPhone.prop('disabled', !enabled);
        $testButton.prop('disabled', !enabled);
        $sendButton.prop('disabled', !enabled);
    }

    /**
     * Escape HTML special characters.
     *
     * @param {string} value
     *
     * @return {string}
     */
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    document.addEventListener('DOMContentLoaded', init);

    return {
        init,
    };
})();
