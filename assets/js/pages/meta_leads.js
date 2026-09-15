/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Meta Leads admin page (internal call workflow).
 * ---------------------------------------------------------------------------- */

/**
 * Meta Leads page.
 *
 * Lists leads received from Meta Lead Ads as an internal call workflow for the
 * receptionists: quick call_status filters, inline status/note editing and a
 * phone-first layout on mobile. No Meta Conversions API calls happen here.
 */
App.Pages.MetaLeads = (function () {
    const $callFilter = $('#meta-leads-call-filter');
    const $keyword = $('#meta-leads-keyword');
    const $filter = $('#meta-leads-filter');
    const $tableBody = $('#meta-leads-table-body');
    const $cards = $('#meta-leads-cards');
    const $empty = $('#meta-leads-empty');

    const CALL_STATUSES = ['de sunat', 'nu a raspuns', 'revine', 'nu e interesat'];

    let currentCallStatus = 'de sunat';

    /**
     * Initialize the page.
     */
    function init() {
        bindEvents();
        renderFilterState();
        load();
    }

    function bindEvents() {
        $callFilter.on('click', 'button', onFilterClick);
        $filter.on('click', load);
        $keyword.on('keyup', (event) => {
            if (event.key === 'Enter') {
                load();
            }
        });

        $tableBody.on('change', '.meta-leads-call-status', onStatusChange);
        $cards.on('change', '.meta-leads-call-status', onStatusChange);

        $tableBody.on('click', '[data-action="save-note"]', onNoteSave);
        $cards.on('click', '[data-action="save-note"]', onNoteSave);

        $tableBody.on('keyup', '.meta-leads-note-input', onNoteKeyup);
        $cards.on('keyup', '.meta-leads-note-input', onNoteKeyup);

        $tableBody.on('click', '[data-action="toggle-form-fields"]', onToggleFormFields);

        $tableBody.on('click', '[data-action="delete"]', onDeleteClick);
        $cards.on('click', '[data-action="delete"]', onDeleteClick);
    }

    function onFilterClick(event) {
        currentCallStatus = $(event.currentTarget).data('call-status') || 'de sunat';
        renderFilterState();
        load();
    }

    function renderFilterState() {
        $callFilter.find('button').each(function () {
            const active = ($(this).data('call-status') || 'de sunat') === currentCallStatus;
            $(this).toggleClass('btn-primary', active).toggleClass('btn-outline-primary', !active);
        });
    }

    function load() {
        const keyword = $keyword.val().trim();

        App.Http.MetaLeads.searchCalls(currentCallStatus, keyword, 200, 0)
            .done((leads) => render(leads || []))
            .fail(() => render([]));
    }

    function render(leads) {
        $tableBody.empty();
        $cards.empty();

        if (!leads.length) {
            $empty.removeClass('d-none');
            return;
        }

        $empty.addClass('d-none');

        leads.forEach((lead) => {
            $tableBody.append(renderTableRow(lead));
            $cards.append(renderCard(lead));
        });
    }

    function onStatusChange(event) {
        const $select = $(event.currentTarget);
        const leadId = $select.data('id');

        App.Http.MetaLeads.updateCall(leadId, { call_status: $select.val() })
            .done(() => load())
            .fail(() => App.Layouts.Backend.displayNotification(lang('service_communication_error')));
    }

    function onNoteSave(event) {
        const $button = $(event.currentTarget);
        const $input = $button.siblings('.meta-leads-note-input');
        const leadId = $button.data('id');

        App.Http.MetaLeads.updateCall(leadId, { call_note: $input.val() })
            .done(() => load())
            .fail(() => App.Layouts.Backend.displayNotification(lang('service_communication_error')));
    }

    function onNoteKeyup(event) {
        if (event.key === 'Enter') {
            $(event.currentTarget).siblings('[data-action="save-note"]').trigger('click');
        }
    }

    function onToggleFormFields(event) {
        const $toggle = $(event.currentTarget);
        const $fields = $toggle.siblings('.meta-leads-more-fields');
        const expand = $fields.hasClass('d-none');

        $fields.toggleClass('d-none', !expand);
        $toggle.find('i').toggleClass('fa-chevron-down', !expand).toggleClass('fa-chevron-up', expand);
        $toggle.find('.meta-leads-more-label').text(expand ? lang('meta_leads_show_less') : $toggle.data('more-label'));
    }

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

    // --- Renderers -----------------------------------------------------------

    function renderTableRow(lead) {
        return `
            <tr>
                <td>${nameAndPhoneHtml(lead)}</td>
                <td>${formAnswersHtml(lead, true)}</td>
                <td>${relativeTimeHtml(lead.received_at)}</td>
                <td>${statusSelectHtml(lead)}</td>
                <td>${assignedToHtml(lead)} ${appointmentBadgeHtml(lead)}</td>
                <td class="text-nowrap">${noteEditorHtml(lead)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${lead.id}" title="${lang('meta_leads_delete')}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
    }

    function renderCard(lead) {
        return `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold">${nameHtml(lead)}</div>
                            <div class="text-muted small">${relativeTimeHtml(lead.received_at)}</div>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${lead.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>

                    ${phoneButtonHtml(lead)}

                    <div class="mb-2">${formAnswersHtml(lead)}</div>

                    <div class="mb-2">${statusSelectHtml(lead)}</div>

                    <div class="mb-2 small">
                        ${assignedToHtml(lead)} ${appointmentBadgeHtml(lead)}
                    </div>

                    ${noteEditorHtml(lead)}
                </div>
            </div>`;
    }

    function nameHtml(lead) {
        const name = [(lead.first_name || ''), (lead.last_name || '')].filter(Boolean).join(' ') || '—';
        return escapeHtml(name);
    }

    function nameAndPhoneHtml(lead) {
        let phone = '';

        if (lead.phone_number) {
            phone = `<a href="${phoneLinkHref(lead.phone_number)}" class="d-block small text-decoration-none"><i class="fas fa-phone me-1"></i>${escapeHtml(lead.phone_number)}</a>`;
        }

        return `<div>${nameHtml(lead)}${phone}</div>`;
    }

    function phoneButtonHtml(lead) {
        if (!lead.phone_number) {
            return `<div class="text-muted mb-2">${lang('no_phone')}</div>`;
        }

        return `<a class="btn btn-success w-100 mb-2" href="${phoneLinkHref(lead.phone_number)}"><i class="fas fa-phone me-2"></i>${escapeHtml(lead.phone_number)}</a>`;
    }

    function phoneLinkHref(phone) {
        return 'tel:' + String(phone).replace(/[^+\d]/g, '');
    }

    function relativeTimeHtml(value) {
        if (!value) {
            return '—';
        }

        return `<span title="${escapeHtml(formatDatetime(value))}">${escapeHtml(formatRelativeTime(value))}</span>`;
    }

    function formAnswersHtml(lead, collapse = false) {
        const fields = parseFormFields(lead.form_fields).filter((field) => {
            const name = String(field.name || '').toLowerCase();

            return name !== 'full_name' && name !== 'phone_number';
        });

        if (!fields.length) {
            return `<span class="text-muted">${lang('meta_leads_no_form_fields')}</span>`;
        }

        const renderField = (field) =>
            `<div class="small"><span class="text-muted">${escapeHtml(cleanQuestion(field.name))}:</span> ${escapeHtml(cleanValue(field.value))}</div>`;

        if (!collapse || fields.length <= 3) {
            return fields.map(renderField).join('');
        }

        const moreLabel = `${lang('meta_leads_show_more')} (${fields.length - 3})`;

        return `
            ${fields.slice(0, 3).map(renderField).join('')}
            <button type="button" class="btn btn-link btn-sm p-0 small text-decoration-none" data-action="toggle-form-fields" data-more-label="${escapeHtml(moreLabel)}">
                <i class="fas fa-chevron-down me-1"></i><span class="meta-leads-more-label">${escapeHtml(moreLabel)}</span>
            </button>
            <div class="meta-leads-more-fields d-none">${fields.slice(3).map(renderField).join('')}</div>`;
    }

    function statusSelectHtml(lead) {
        const options = CALL_STATUSES.map((status) => {
            const selected = lead.call_status === status ? 'selected' : '';

            return `<option value="${status}" ${selected}>${lang('call_status_' + status.replace(/ /g, '_'))}</option>`;
        }).join('');

        return `<select class="form-select form-select-sm meta-leads-call-status" data-id="${lead.id}">${options}</select>`;
    }

    function noteEditorHtml(lead) {
        return `
            <div class="input-group input-group-sm">
                <input type="text" class="form-control meta-leads-note-input" value="${escapeHtml(lead.call_note || '')}" placeholder="${lang('call_note_placeholder')}">
                <button type="button" class="btn btn-outline-secondary" data-action="save-note" data-id="${lead.id}">
                    <i class="fas fa-check"></i>
                </button>
            </div>`;
    }

    function assignedToHtml(lead) {
        return `<span class="text-muted">${escapeHtml(lead.assigned_to_name || lang('unassigned'))}</span>`;
    }

    function appointmentBadgeHtml(lead) {
        const hasAppointment = Number(lead.has_appointments) > 0;

        return hasAppointment ? `<span class="badge bg-success ms-1">${lang('has_appointment')}</span>` : '';
    }

    function parseFormFields(formFields) {
        if (!formFields || typeof formFields !== 'string') {
            return [];
        }

        const separator = formFields.indexOf('||');

        if (separator === -1) {
            return [];
        }

        const names = formFields.slice(0, separator).split(' | ');
        const values = formFields.slice(separator + 2).split(' | ');

        const count = Math.min(names.length, values.length);
        const fields = [];

        for (let i = 0; i < count; i++) {
            fields.push({
                name: names[i].trim(),
                value: values[i].trim(),
            });
        }

        return fields;
    }

    function cleanQuestion(name) {
        const spaced = String(name || '').replace(/_/g, ' ');

        return spaced.charAt(0).toUpperCase() + spaced.slice(1);
    }

    function cleanValue(value) {
        return String(value || '').replace(/_/g, ' ');
    }

    function formatRelativeTime(value) {
        const then = moment(value);

        if (!then.isValid()) {
            return '—';
        }

        const seconds = moment().diff(then, 'seconds');

        if (seconds < 60) {
            return lang('relative_time_just_now');
        }

        const minutes = Math.floor(seconds / 60);

        if (minutes < 60) {
            return fillRelative(lang('relative_time_minutes'), minutes);
        }

        const hours = Math.floor(minutes / 60);

        if (hours < 24) {
            return fillRelative(lang('relative_time_hours'), hours);
        }

        const days = Math.floor(hours / 24);

        if (days < 7) {
            return fillRelative(lang('relative_time_days'), days);
        }

        if (days < 30) {
            return fillRelative(lang('relative_time_weeks'), Math.floor(days / 7));
        }

        if (days < 365) {
            return fillRelative(lang('relative_time_months'), Math.floor(days / 30));
        }

        return fillRelative(lang('relative_time_years'), Math.floor(days / 365));
    }

    function fillRelative(template, count) {
        return String(template).replace('%d', count);
    }

    function formatDatetime(value) {
        if (!value) {
            return '—';
        }

        return moment(value).format('DD.MM.YYYY HH:mm');
    }

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
