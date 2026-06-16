<?php
/**
 * Revclar dark + indigo theme overrides for the backend UI.
 *
 * Loaded after backend.css so these rules win without fighting the minified bundle.
 */
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
    /* -------------------------------------------------------------------------
     * Bootstrap variable overrides (dark mode)
     * ------------------------------------------------------------------------- */
    [data-bs-theme="dark"] {
        /* Indigo accent */
        --bs-primary: #6366F1;
        --bs-primary-rgb: 99, 102, 241;
        --bs-primary-text-emphasis: #818CF8;
        --bs-primary-bg-subtle: rgba(99, 102, 241, 0.15);
        --bs-primary-border-subtle: rgba(99, 102, 241, 0.4);

        /* Dark Revclar palette */
        --bs-body-bg: #09090B;
        --bs-body-bg-rgb: 9, 9, 11;
        --bs-body-color: #FAFAFA;
        --bs-body-color-rgb: 250, 250, 250;

        --bs-secondary-color: #A1A1AA;
        --bs-secondary-color-rgb: 161, 161, 170;
        --bs-secondary-bg: #1F1F23;
        --bs-secondary-bg-rgb: 31, 31, 35;

        --bs-tertiary-bg: #26262B;
        --bs-tertiary-bg-rgb: 38, 38, 43;

        --bs-border-color: rgba(255, 255, 255, 0.16);
        --bs-border-color-translucent: rgba(255, 255, 255, 0.16);

        /* Revclar surface palette */
        --revclar-page-bg: #09090B;
        --revclar-surface: #1F1F23;
        --revclar-surface-2: #26262B;
        --revclar-border: rgba(255, 255, 255, 0.16);
        --revclar-border-strong: rgba(255, 255, 255, 0.24);

        /* Links */
        --bs-link-color: #818CF8;
        --bs-link-hover-color: #A5B4FC;
        --bs-link-color-rgb: 129, 140, 248;
        --bs-link-hover-color-rgb: 165, 180, 252;

        /* Emphasis */
        --bs-emphasis-color: #FAFAFA;
        --bs-emphasis-color-rgb: 250, 250, 250;

        /* Focus ring */
        --bs-focus-ring-color: rgba(99, 102, 241, 0.35);
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: #FAFAFA;
    }

    code, pre, .font-monospace {
        font-family: 'JetBrains Mono', monospace;
    }

    /* -------------------------------------------------------------------------
     * Global text / headings
     *
     * Make every heading and label clearly visible on the dark background.
     * ------------------------------------------------------------------------- */
    h1, h2, h3, h4, h5, h6,
    .h1, .h2, .h3, .h4, .h5, .h6,
    .card-title, .modal-title, .modal-header h3 {
        color: #FAFAFA;
    }

    label, .form-label, .col-form-label, legend,
    .form-text, small, .small {
        color: #E4E4E7;
    }

    .text-muted {
        color: #A1A1AA !important;
    }

    /* -------------------------------------------------------------------------
     * Header / navigation
     * ------------------------------------------------------------------------- */
    #header {
        background-color: #1F1F23 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.16);
    }

    #header #header-menu .nav-item:hover {
        background: rgba(99, 102, 241, 0.12);
    }

    #header #header-menu .nav-item.active {
        background: rgba(99, 102, 241, 0.22);
        box-shadow: inset 0 -2px 0 0 #6366F1;
    }

    #header .nav-link,
    #header .navbar-brand,
    #header .navbar-toggler-icon {
        color: #FAFAFA !important;
    }

    #header .text-white-50 {
        color: #A1A1AA !important;
    }

    /* -------------------------------------------------------------------------
     * Cards, modals, dropdowns
     * ------------------------------------------------------------------------- */
    .card,
    .modal-content,
    .dropdown-menu,
    .popover {
        background-color: #1F1F23;
        border-color: rgba(255, 255, 255, 0.16);
        color: #FAFAFA;
    }

    .card-header,
    .card-footer {
        background-color: #1F1F23;
        border-color: rgba(255, 255, 255, 0.16);
    }

    .modal-header {
        background-color: #6366F1;
        border-bottom-color: rgba(255, 255, 255, 0.16);
    }

    .modal-header h3,
    .modal-title {
        color: #FAFAFA;
    }

    .dropdown-item {
        color: #FAFAFA;
    }

    .dropdown-item:hover,
    .dropdown-item:focus,
    .dropdown-item.active,
    .dropdown-item:active {
        background-color: #6366F1;
        color: #FAFAFA;
    }

    .dropdown-divider {
        border-color: rgba(255, 255, 255, 0.16);
    }

    /* -------------------------------------------------------------------------
     * Native selects and Select2 autocomplete dropdowns
     * ------------------------------------------------------------------------- */
    select option,
    select optgroup,
    .form-select option,
    .form-select optgroup {
        background-color: #1F1F23;
        color: #FAFAFA;
    }

    select option:hover,
    select option:focus,
    select option:checked,
    select option:active,
    .form-select option:hover,
    .form-select option:focus,
    .form-select option:checked,
    .form-select option:active {
        background-color: #6366F1;
        color: #FAFAFA;
    }

    .select2-dropdown {
        background-color: #1F1F23;
        border-color: rgba(255, 255, 255, 0.16);
    }

    .select2-container--default .select2-selection--single,
    .select2-container--default .select2-selection--multiple {
        background-color: #1F1F23;
        border-color: rgba(255, 255, 255, 0.16);
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered,
    .select2-container--default .select2-selection--single .select2-selection__placeholder,
    .select2-container--default .select2-selection--multiple .select2-selection__rendered,
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        color: #FAFAFA;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #FAFAFA transparent transparent transparent;
    }

    .select2-container--default .select2-results__option {
        color: #FAFAFA;
    }

    .select2-container--default .select2-results__option--highlighted,
    .select2-container--default .select2-results__option--selected,
    .select2-container--default .select2-results__option[aria-selected="true"] {
        background-color: #6366F1;
        color: #FAFAFA;
    }

    .select2-container--default .select2-search--dropdown .select2-search__field {
        background-color: #1F1F23;
        border-color: rgba(255, 255, 255, 0.16);
        color: #FAFAFA;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #6366F1;
        border-color: rgba(255, 255, 255, 0.16);
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #FAFAFA;
    }

    .select2-container--default.select2-container--focus .select2-selection--multiple,
    .select2-container--default.select2-container--open .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--multiple {
        border-color: #6366F1;
    }

    /* -------------------------------------------------------------------------
     * Forms
     * ------------------------------------------------------------------------- */
    .form-control,
    .form-select {
        background-color: #1F1F23;
        border-color: rgba(255, 255, 255, 0.16);
        color: #FAFAFA;
    }

    .form-control:focus,
    .form-select:focus {
        background-color: #1F1F23;
        border-color: #6366F1;
        box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        color: #FAFAFA;
    }

    .form-control::placeholder {
        color: #A1A1AA;
    }

    .form-control:disabled,
    .form-control[readonly],
    .backend-page .form-control:disabled,
    .backend-page .form-control[readonly] {
        background-color: #0e0e10;
        color: #A1A1AA;
    }

    .form-check-input:checked {
        background-color: #6366F1;
        border-color: #6366F1;
    }

    .form-check-input:focus {
        border-color: #6366F1;
        box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
    }

    .input-group-text {
        background-color: #26262B;
        border-color: rgba(255, 255, 255, 0.16);
        color: #FAFAFA;
    }

    /* -------------------------------------------------------------------------
     * Tables
     * ------------------------------------------------------------------------- */
    .table {
        --bs-table-bg: #1F1F23;
        --bs-table-color: #FAFAFA;
        --bs-table-border-color: rgba(255, 255, 255, 0.16);
    }

    .table-hover > tbody > tr:hover {
        --bs-table-bg-state: rgba(99, 102, 241, 0.08);
    }

    .list-group-item {
        background-color: #1F1F23;
        border-color: rgba(255, 255, 255, 0.16);
        color: #FAFAFA;
    }

    .list-group-item-action:hover,
    .list-group-item-action:focus {
        background-color: #26262B;
        color: #FAFAFA;
    }

    .list-group-item.active {
        background-color: #6366F1;
        border-color: #6366F1;
        color: #FAFAFA;
    }

    /* -------------------------------------------------------------------------
     * Calendar
     * ------------------------------------------------------------------------- */
    #calendar table thead .fc-first,
    #calendar .calendar-view .date-column .date-column-title,
    #calendar .calendar-view .date-column .provider-column h6 {
        background: #1F1F23;
        color: #FAFAFA;
        border-color: rgba(255, 255, 255, 0.16);
    }

    #calendar .fc-event {
        border-color: #6366F1;
        background-color: #6366F1;
        color: #FAFAFA;
    }

    #calendar .fc-unavailability {
        background-color: #1F1F23;
        color: #A1A1AA;
    }

    #calendar .fc-daygrid-day-number,
    #calendar .fc-daygrid-day-number:hover,
    #calendar .fc-daygrid-day-number:focus {
        color: #FAFAFA !important;
    }

    #calendar .fc-col-header-cell-cushion {
        color: #FAFAFA !important;
    }

    /* Table view column layout (makes sure providers are side-by-side) */
    #calendar .calendar-view {
        overflow-x: auto;
        overflow-y: hidden;
    }

    #calendar .calendar-view > div {
        display: flex;
        flex-wrap: nowrap;
        width: max-content;
        min-width: 100%;
    }

    #calendar .calendar-view .date-column {
        display: flex;
        flex-wrap: nowrap;
        flex-shrink: 0;
    }

    #calendar .calendar-view .date-column .date-column-title {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        transform: rotate(180deg);
        padding: 10px 5px;
        margin: 0;
        background: #1F1F23;
        border-right: 1px solid rgba(255, 255, 255, 0.16);
        white-space: nowrap;
    }

    #calendar .calendar-view .date-column .provider-column {
        flex-shrink: 0;
        width: 350px;
        min-width: 350px;
        max-width: 350px;
        border-right: 1px solid rgba(255, 255, 255, 0.16);
    }

    #calendar .calendar-view .date-column .provider-column h6 {
        padding: 8px 10px;
        margin: 0;
        background: #1F1F23;
        border-bottom: 1px solid rgba(255, 255, 255, 0.16);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* -------------------------------------------------------------------------
     * Backend page panels (Customers list/details, Settings cards, etc.)
     * ------------------------------------------------------------------------- */
    .backend-page .filter-records,
    .backend-page .record-details,
    #customer-appointments {
        background-color: #1F1F23;
        border: 1px solid rgba(255, 255, 255, 0.16);
        border-radius: 0.5rem;
    }

    .backend-page .filter-records .results .entry {
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .backend-page .filter-records .results .entry:last-child {
        border-bottom: none;
    }

    /* -------------------------------------------------------------------------
     * Buttons
     * ------------------------------------------------------------------------- */
    .btn-primary,
    .btn-success,
    .btn-primary:hover,
    .btn-success:hover,
    .btn-primary:focus,
    .btn-success:focus,
    .btn-primary:active,
    .btn-success:active,
    .btn-primary.active,
    .btn-success.active {
        color: #FAFAFA !important;
    }

    .btn-primary,
    .btn-success {
        background-image: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%);
        background-color: transparent;
        border-color: transparent;
    }

    .btn-primary:hover,
    .btn-primary:focus,
    .btn-primary:active,
    .btn-success:hover,
    .btn-success:focus,
    .btn-success:active {
        background-image: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        background-color: transparent;
        border-color: transparent;
    }

    .btn-primary:disabled,
    .btn-success:disabled {
        background-image: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%);
        border-color: transparent;
        opacity: 0.65;
    }

    /* -------------------------------------------------------------------------
     * Nav pills / tabs (provider/customer details tabs use Bootstrap pills)
     * ------------------------------------------------------------------------- */
    .nav-pills {
        --bs-nav-pills-link-active-color: #FAFAFA;
        --bs-nav-pills-link-active-bg: #6366F1;
    }

    .nav-pills .nav-link:not(.active) {
        color: #E4E4E7;
    }

    .nav-pills .nav-link:not(.active):hover,
    .nav-pills .nav-link:not(.active):focus {
        color: #FAFAFA;
        background-color: rgba(255, 255, 255, 0.08);
    }

    .nav-tabs {
        --bs-nav-tabs-link-active-color: #FAFAFA;
        --bs-nav-tabs-link-active-bg: #1F1F23;
        --bs-nav-tabs-link-active-border-color: rgba(255, 255, 255, 0.16);
        border-bottom-color: rgba(255, 255, 255, 0.16);
    }

    .nav-tabs .nav-link {
        color: #E4E4E7;
    }

    .nav-tabs .nav-link:hover,
    .nav-tabs .nav-link:focus {
        color: #FAFAFA;
        border-color: rgba(255, 255, 255, 0.16);
    }

    .btn-outline-primary {
        --bs-btn-color: #6366F1;
        --bs-btn-border-color: #6366F1;
        --bs-btn-hover-bg: #6366F1;
        --bs-btn-hover-border-color: #6366F1;
        --bs-btn-active-bg: #4F46E5;
        --bs-btn-active-border-color: #4F46E5;
    }

    /* -------------------------------------------------------------------------
     * Footer & loading
     * ------------------------------------------------------------------------- */
    #footer {
        background-color: #09090B !important;
        border-top-color: rgba(255, 255, 255, 0.16) !important;
    }

    #loading {
        background: rgba(9, 9, 11, 0.9) !important;
    }

    /* -------------------------------------------------------------------------
     * Utility helpers
     * ------------------------------------------------------------------------- */
    .bg-light {
        background-color: #1F1F23 !important;
    }

    .bg-white {
        background-color: #1F1F23 !important;
    }

    .border,
    .border-top,
    .border-bottom,
    .border-start,
    .border-end {
        border-color: rgba(255, 255, 255, 0.16) !important;
    }

    .text-muted {
        color: #A1A1AA !important;
    }

    .text-dark,
    .text-black,
    .text-body,
    .text-reset {
        color: #FAFAFA !important;
    }

    .text-secondary {
        color: #E4E4E7 !important;
    }

    /* Outline buttons: light text/borders so they remain visible on dark bg */
    .btn-outline-secondary {
        --bs-btn-color: #E4E4E7;
        --bs-btn-border-color: rgba(255, 255, 255, 0.25);
        --bs-btn-hover-color: #FAFAFA;
        --bs-btn-hover-bg: rgba(255, 255, 255, 0.08);
        --bs-btn-hover-border-color: rgba(255, 255, 255, 0.35);
        --bs-btn-active-color: #FAFAFA;
        --bs-btn-active-bg: rgba(255, 255, 255, 0.12);
        --bs-btn-active-border-color: rgba(255, 255, 255, 0.4);
    }

    .btn-outline-primary {
        --bs-btn-color: #A5B4FC;
        --bs-btn-border-color: #818CF8;
        --bs-btn-hover-color: #FAFAFA;
        --bs-btn-hover-bg: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%);
        --bs-btn-hover-border-color: transparent;
        --bs-btn-active-color: #FAFAFA;
        --bs-btn-active-bg: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        --bs-btn-active-border-color: transparent;
    }

    /* Trumbowyg editor dark tweaks */
    .trumbowyg-box,
    .trumbowyg-editor {
        background-color: #1F1F23;
        border-color: rgba(255, 255, 255, 0.16);
        color: #FAFAFA;
    }

    .trumbowyg-button-pane {
        background-color: #1F1F23;
        border-bottom-color: rgba(255, 255, 255, 0.16);
    }

    /* -------------------------------------------------------------------------
     * Mobile-first responsive tweaks
     * ------------------------------------------------------------------------- */
    @media (max-width: 767.98px) {
        /* Header: keep only icons to save space */
        #header .navbar-brand span {
            display: none;
        }

        #header #header-menu .nav-link span {
            display: none;
        }

        /* Calendar toolbar: stack controls vertically */
        #calendar-toolbar .calendar-header,
        #calendar .calendar-header {
            flex-direction: column !important;
            gap: 0.5rem;
        }

        #calendar-toolbar .calendar-header > *,
        #calendar .calendar-header > * {
            width: 100% !important;
            max-width: 100% !important;
        }

        #calendar-toolbar .input-group,
        #calendar-toolbar .form-select,
        #calendar-toolbar .btn,
        #calendar .calendar-header .btn,
        #calendar .calendar-header .form-select {
            width: 100% !important;
        }

        /* Calendar: no horizontal scroll and full-width provider columns on mobile */
        #calendar .calendar-view {
            overflow-x: hidden !important;
        }

        #calendar .calendar-view > div {
            min-width: auto !important;
        }

        #calendar .calendar-view .date-column .provider-column {
            width: 100% !important;
            min-width: 100% !important;
            max-width: 100% !important;
            border-right: none !important;
            margin-bottom: 1rem;
        }

        #calendar .calendar-view .date-column-title {
            display: none !important;
        }

        #calendar .calendar-wrapper {
            max-height: 70vh !important;
        }

        /* Backend forms: single column on mobile */
        .backend-page .row .col,
        .backend-page .row [class*="col-"] {
            width: 100%;
            max-width: 100%;
            flex: 0 0 100%;
        }

        /* Public booking page: full-width controls */
        #wizard-frame-1 .form-select,
        #wizard-frame-1 .form-control,
        #wizard-frame-1 .btn,
        #wizard-frame-2 .form-select,
        #wizard-frame-2 .form-control,
        #wizard-frame-2 .btn,
        #wizard-frame-3 .form-select,
        #wizard-frame-3 .form-control,
        #wizard-frame-3 .btn {
            width: 100%;
            min-height: 48px;
        }

        /* Larger touch targets for buttons and nav */
        .btn,
        .nav-link,
        .dropdown-item,
        .form-check-input {
            min-height: 44px;
        }
    }
</style>
