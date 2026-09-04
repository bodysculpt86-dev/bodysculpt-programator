/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Meta Leads admin page.
 * ---------------------------------------------------------------------------- */

/**
 * Meta Leads page.
 *
 * Lists leads received from Meta Lead Ads, lets admins inspect the raw form
 * answers and delete leads.
 */
App.Pages.MetaLeads = (function () {
    const $statusFilter = $('#meta-leads-status-filter');
    const $keyword = $('#meta-leads-keyword');
    const $filter = $('#meta-leads-filter');
    const $resultsBody = $('#meta-leads-results-body');
    const $empty = $('#meta-leads-empty');

    let detailsModal = null;

    /**
     * Initialize the page.
     */
    function init() {
        load();

        $filter.on('click', load);
        $statusFilter.on('change', load);
        $keyword.on('keyup', (event) => {
            if (event.key === 'Enter') {
                load();
            }
        });

        $resultsBody.on('click', '[data-action="view"]', onViewClick);
        $resultsBody.on('click', '[data-action="delete"]', onDeleteClick);
    }

    /**
     * Load and render the current filter results.
     */
    function load() {
        const keyword = $keyword.val().trim();
        const status = $statusFilter.val();

        App.Http.MetaLeads.search(keyword, status || null, 100, 0)
            .done((leads) => {
                render(leads || []);
            })
            .fail(() => {
                render([]);
            });
    }

    /**
     * Render the leads table.
     *
     * @param {Array} leads
     */
    function render(leads) {
        $resultsBody.empty();

        if (!leads.length) {
            $empty.removeClass('d-none');

            return;
        }

        $empty.addClass('d-none');

        leads.forEach((lead) => {
            const name = escapeHtml(
                [(lead.first_name || ''), (lead.last_name || '')].filter(Boolean).join(' ') || '—',
            );

            const statusBadge =
                lead.status === 'converted'
                    ? `<span class="badge bg-success">${lang('meta_leads_status_converted')}</span>`
                    : `<span class="badge bg-info text-dark">${lang('meta_leads_status_new')}</span>`;

            $resultsBody.append(`
                <tr>
                    <td>${name}</td>
                    <td>${escapeHtml(lead.phone_number || '')}</td>
                    <td>${escapeHtml(lead.email || '')}</td>
                    <td>${escapeHtml(formatDatetime(lead.received_at))}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-action="view" data-id="${lead.id}">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${lead.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    /**
     * Handle the "view" action: show the raw form answers in a modal.
     *
     * @param {jQuery.Event} event
     */
    function onViewClick(event) {
        const leadId = $(event.currentTarget).data('id');

        App.Http.MetaLeads.show(leadId)
            .done((lead) => {
                showDetailsModal(lead);
            })
            .fail(() => {
                App.Layouts.Backend.displayNotification(lang('service_communication_error'));
            });
    }

    /**
     * Handle the "delete" action.
     *
     * @param {jQuery.Event} event
     */
    function onDeleteClick(event) {
        const leadId = $(event.currentTarget).data('id');

        App.Utils.Message.show(lang('meta_leads_delete'), lang('meta_leads_delete_confirm'), [
            {
                text: lang('cancel'),
                click: (event, messageModal) => messageModal.hide(),
            },
            {
                text: lang('meta_leads_delete'),
                click: (event, messageModal) => {
                    messageModal.hide();

                    App.Http.MetaLeads.destroy(leadId)
                        .done(() => load())
                        .fail(() => App.Layouts.Backend.displayNotification(lang('service_communication_error')));
                },
            },
        ]);
    }

    /**
     * Build and show a modal listing the lead's raw form answers.
     *
     * @param {Object} lead
     */
    function showDetailsModal(lead) {
        if (detailsModal) {
            detailsModal.remove();
        }

        const fields = lead.form_fields || [];
        const rows = fields.length
            ? fields
                  .map((field) => {
                      const value = (field.values || []).join(', ');

                      return `<dt>${escapeHtml(field.name || '')}</dt><dd>${escapeHtml(value)}</dd>`;
                  })
                  .join('')
            : `<p class="text-muted">${lang('meta_leads_no_form_fields')}</p>`;

        detailsModal = $(`
            <div class="modal fade" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${lang('meta_leads_view_title')}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <dl class="row mb-0">
                                <dt class="col-4">${lang('first_name')}</dt><dd class="col-8">${escapeHtml(lead.first_name || '')}</dd>
                                <dt class="col-4">${lang('last_name')}</dt><dd class="col-8">${escapeHtml(lead.last_name || '')}</dd>
                                <dt class="col-4">${lang('phone_number')}</dt><dd class="col-8">${escapeHtml(lead.phone_number || '')}</dd>
                                <dt class="col-4">${lang('email')}</dt><dd class="col-8">${escapeHtml(lead.email || '')}</dd>
                            </dl>
                            <hr>
                            ${rows}
                        </div>
                    </div>
                </div>
            </div>
        `).appendTo('body');

        detailsModal.modal('show');
    }

    /**
     * Format a "YYYY-MM-DD HH:mm:ss" datetime for display.
     *
     * @param {String} value
     *
     * @return {String}
     */
    function formatDatetime(value) {
        if (!value) {
            return '—';
        }

        return moment(value).format('DD.MM.YYYY HH:mm');
    }

    /**
     * Escape HTML special characters.
     *
     * @param {String} value
     *
     * @return {String}
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
