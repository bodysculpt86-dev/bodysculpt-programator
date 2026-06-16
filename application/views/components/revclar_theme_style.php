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
        --bs-secondary-bg: #18181B;
        --bs-secondary-bg-rgb: 24, 24, 27;

        --bs-tertiary-bg: #18181B;
        --bs-tertiary-bg-rgb: 24, 24, 27;

        --bs-border-color: rgba(255, 255, 255, 0.08);
        --bs-border-color-translucent: rgba(255, 255, 255, 0.08);

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
    }

    code, pre, .font-monospace {
        font-family: 'JetBrains Mono', monospace;
    }

    /* -------------------------------------------------------------------------
     * Header / navigation
     * ------------------------------------------------------------------------- */
    #header {
        background-color: #18181B !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
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
        background-color: #18181B;
        border-color: rgba(255, 255, 255, 0.08);
        color: #FAFAFA;
    }

    .card-header,
    .card-footer {
        background-color: #18181B;
        border-color: rgba(255, 255, 255, 0.08);
    }

    .modal-header {
        background-color: #6366F1;
        border-bottom-color: rgba(255, 255, 255, 0.08);
    }

    .modal-header h3,
    .modal-title {
        color: #FAFAFA;
    }

    .dropdown-item {
        color: #FAFAFA;
    }

    .dropdown-item:hover,
    .dropdown-item:focus {
        background-color: rgba(99, 102, 241, 0.12);
        color: #FAFAFA;
    }

    .dropdown-divider {
        border-color: rgba(255, 255, 255, 0.08);
    }

    /* -------------------------------------------------------------------------
     * Forms
     * ------------------------------------------------------------------------- */
    .form-control,
    .form-select {
        background-color: #18181B;
        border-color: rgba(255, 255, 255, 0.08);
        color: #FAFAFA;
    }

    .form-control:focus,
    .form-select:focus {
        background-color: #18181B;
        border-color: #6366F1;
        box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        color: #FAFAFA;
    }

    .form-control::placeholder {
        color: #A1A1AA;
    }

    .form-control:disabled,
    .form-control[readonly] {
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

    /* -------------------------------------------------------------------------
     * Tables
     * ------------------------------------------------------------------------- */
    .table {
        --bs-table-bg: #18181B;
        --bs-table-color: #FAFAFA;
        --bs-table-border-color: rgba(255, 255, 255, 0.08);
    }

    .table-hover > tbody > tr:hover {
        --bs-table-bg-state: rgba(99, 102, 241, 0.08);
    }

    /* -------------------------------------------------------------------------
     * Calendar
     * ------------------------------------------------------------------------- */
    #calendar table thead .fc-first,
    #calendar .calendar-view .date-column .date-column-title,
    #calendar .calendar-view .date-column .provider-column h6 {
        background: #18181B;
        color: #FAFAFA;
        border-color: rgba(255, 255, 255, 0.08);
    }

    #calendar .fc-event {
        border-color: #6366F1;
        background-color: #6366F1;
        color: #FAFAFA;
    }

    #calendar .fc-unavailability {
        background-color: #18181B;
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

    /* -------------------------------------------------------------------------
     * Buttons
     * ------------------------------------------------------------------------- */
    .btn-primary {
        --bs-btn-bg: #6366F1;
        --bs-btn-border-color: #6366F1;
        --bs-btn-hover-bg: #4F46E5;
        --bs-btn-hover-border-color: #4F46E5;
        --bs-btn-active-bg: #4338CA;
        --bs-btn-active-border-color: #4338CA;
        --bs-btn-disabled-bg: #6366F1;
        --bs-btn-disabled-border-color: #6366F1;
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
        border-top-color: rgba(255, 255, 255, 0.08) !important;
    }

    #loading {
        background: rgba(9, 9, 11, 0.9) !important;
    }

    /* -------------------------------------------------------------------------
     * Utility helpers
     * ------------------------------------------------------------------------- */
    .bg-light {
        background-color: #18181B !important;
    }

    .border,
    .border-top,
    .border-bottom,
    .border-start,
    .border-end {
        border-color: rgba(255, 255, 255, 0.08) !important;
    }

    .text-muted {
        color: #A1A1AA !important;
    }

    /* Trumbowyg editor dark tweaks */
    .trumbowyg-box,
    .trumbowyg-editor {
        background-color: #18181B;
        border-color: rgba(255, 255, 255, 0.08);
        color: #FAFAFA;
    }

    .trumbowyg-button-pane {
        background-color: #18181B;
        border-bottom-color: rgba(255, 255, 255, 0.08);
    }
</style>
