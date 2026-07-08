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
     * Header / left sidebar navigation
     * ------------------------------------------------------------------------- */
    .backend-sidebar,
    .backend-mobile-header,
    .offcanvas#header-menu-offcanvas {
        background-color: #1F1F23 !important;
        border-color: rgba(255, 255, 255, 0.16) !important;
    }

    .backend-sidebar-brand,
    .backend-sidebar-footer,
    .backend-sidebar-submenu {
        border-color: rgba(255, 255, 255, 0.16) !important;
    }

    .backend-sidebar-link,
    .backend-sidebar-sublink,
    .backend-sidebar-brand,
    .backend-mobile-brand,
    .backend-mobile-toggle {
        color: #FAFAFA !important;
    }

    .backend-sidebar-link:hover,
    .backend-sidebar-sublink:hover {
        background: rgba(99, 102, 241, 0.12) !important;
    }

    .backend-sidebar-link.active,
    .backend-sidebar-dropdown.active > .backend-sidebar-link,
    .backend-sidebar-submenu {
        background: rgba(99, 102, 241, 0.16) !important;
    }

    .backend-sidebar-link.active,
    .backend-sidebar-dropdown.active > .backend-sidebar-link {
        box-shadow: inset 4px 0 0 0 #6366F1;
    }

    .backend-sidebar-brand small,
    .backend-sidebar-sublink {
        color: #A1A1AA !important;
    }

    .backend-sidebar-sublink:hover {
        color: #FAFAFA !important;
    }

    .backend-sidebar-submenu .backend-sidebar-sublink i {
        color: #A1A1AA;
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
     * Calendar - EvoBeauty-inspired styling
     * ------------------------------------------------------------------------- */

    /* Calendar chrome: light, clean background */
    #calendar .fc-scrollgrid,
    #calendar .fc-timegrid-body,
    #calendar .fc-timegrid-slots table,
    #calendar .fc-timegrid-cols table {
        background-color: #E8F6F0 !important;
    }

    /* Calendar filter header: dark background so near-white labels remain readable */
    #calendar .calendar-header {
        background-color: #1F2937 !important;
        padding: 12px 15px !important;
        border-radius: 6px;
        margin-bottom: 8px;
    }

    /* Provider / day column headers */
    #calendar table thead .fc-first,
    #calendar .calendar-view .date-column .date-column-title,
    #calendar .calendar-view .date-column .provider-column h6 {
        background: #FFFFFF;
        color: #1F2937;
        border-color: #D1D5DB;
        font-weight: 600;
    }

    #calendar .calendar-view .date-column .provider-column h6 {
        border-bottom: 1px solid #D1D5DB;
        text-align: center;
        font-size: 0.9rem;
    }

    /* Vertical date title on the left */
    #calendar .calendar-view .date-column .date-column-title {
        background: #F8FAFC;
        border-right: 1px solid #D1D5DB;
        color: #4B5563;
        font-weight: 600;
    }

    /* Appointment blocks: EvoBeauty blue */
    #calendar .fc-event {
        border-color: #5C9DC0;
        background-color: #5C9DC0;
        color: #FFFFFF;
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
    }

    #calendar .fc-event .fc-event-time {
        color: rgba(255, 255, 255, 0.9);
        font-weight: 600;
    }

    #calendar .fc-event .fc-event-title {
        color: #FFFFFF;
        font-weight: 600;
    }

    /* Unavailability / breaks */
    #calendar .fc-unavailability,
    #calendar .fc-break,
    #calendar .fc-bg-event {
        background-image: none !important;
        background-color: #E5E7EB !important;
        color: #4B5563;
        border: none;
        opacity: 1;
    }

    #calendar .fc-unavailability .fc-event-title,
    #calendar .fc-break .fc-event-title,
    #calendar .fc-bg-event .fc-event-title {
        color: #4B5563;
        font-weight: 500;
    }

    /* Now indicator line */
    #calendar .fc-timegrid-now-indicator-line {
        border-color: #EF4444;
    }

    /* Table view column layout (makes sure providers are side-by-side) */
    #calendar .calendar-view {
        overflow-x: auto;
        overflow-y: visible;
        border: 1px solid #D1D5DB;
        border-radius: 6px;
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
        white-space: nowrap;
    }

    #calendar .calendar-view .date-column .provider-column {
        flex-shrink: 0;
        width: 350px;
        min-width: 350px;
        max-width: 350px;
        border-right: 1px solid #D1D5DB;
    }

    #calendar .calendar-view .date-column .provider-column:last-child {
        border-right: none;
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
     * Responsive: keep desktop layout everywhere, just allow pinch-zoom.
     * The calendar fills the full viewport height vertically and scrolls
     * horizontally when there are many provider columns.
     * ------------------------------------------------------------------------- */
    @media (max-width: 767.98px) {
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

    /* -------------------------------------------------------------------------
     * Calendar page: normal page scrolling, calendar renders its natural height.
     * The calendar itself has no internal vertical scrollbar; if it is taller
     * than the viewport the user scrolls the page normally.
     * ------------------------------------------------------------------------- */
    #calendar-page {
        overflow: visible;
        padding-bottom: 1rem;
    }

    #calendar .calendar-view {
        overflow-x: auto;
        overflow-y: visible;
    }

    #calendar .calendar-view > div {
        display: flex;
    }

    /* Desktop: enable synchronized horizontal scroll when many provider columns
       exceed the viewport. The mobile rule above stays untouched. */
    @media (min-width: 768px) {
        #calendar .calendar-view > div {
            display: flex;
            flex-wrap: nowrap;
            width: max-content;
            min-width: 100%;
        }
    }

    #calendar .date-column {
        display: flex;
        flex-direction: row;
    }

    #calendar .provider-column {
        display: flex;
        flex-direction: column;
        width: 350px;
        min-width: 350px;
        max-width: 350px;
    }

    #calendar .provider-column h6 {
        margin: 0;
        padding: 8px 10px;
    }

    #calendar .calendar-wrapper {
        height: auto !important;
    }

    /* Time slot labels: full hours bold and large, quarters small */
    #calendar .fc-timegrid-slot-label-cushion,
    #calendar .fc-timegrid-axis-cushion {
        font-size: 0.75rem;
        line-height: 1;
        color: #374151;
    }

    /* Full hours: bold, bigger, dark */
    #calendar .fc-timegrid-slot[data-time$=":00:00"] .fc-timegrid-slot-label-cushion {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1F2937;
    }

    /* Quarter hours: smaller, but dark enough to be readable on mint background */
    #calendar .fc-timegrid-slot[data-time$=":15:00"] .fc-timegrid-slot-label-cushion,
    #calendar .fc-timegrid-slot[data-time$=":30:00"] .fc-timegrid-slot-label-cushion,
    #calendar .fc-timegrid-slot[data-time$=":45:00"] .fc-timegrid-slot-label-cushion {
        font-size: 0.7rem;
        font-weight: 400;
        color: #4B5563;
    }

    /* Horizontal separators: strong at full hours, subtle dotted at quarters */
    #calendar .fc-timegrid-slot[data-time$=":00:00"] {
        border-bottom: 1px solid #9CA3AF !important;
    }

    #calendar .fc-timegrid-slot[data-time$=":15:00"],
    #calendar .fc-timegrid-slot[data-time$=":30:00"],
    #calendar .fc-timegrid-slot[data-time$=":45:00"] {
        border-bottom: 1px dotted #D1D5DB !important;
    }

    #calendar .fc-timegrid-slot-lane {
        background-color: #E8F6F0;
    }

    #calendar .fc-timegrid-axis-cushion {
        color: #374151;
    }

    #calendar .fc-event-main,
    #calendar .fc-event-main-frame,
    #calendar .fc-event-time,
    #calendar .fc-event-title-container,
    #calendar .fc-event-title {
        font-size: 0.75rem !important;
        line-height: 1.15 !important;
    }

    #calendar .fc-event-title-container,
    #calendar .fc-event-title {
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    /* FullCalendar view switcher buttons (Listă / Programator) */
    #calendar .fc-button-primary {
        background-color: #FFFFFF;
        border-color: #D1D5DB;
        color: #4B5563;
        font-weight: 500;
        box-shadow: none;
    }

    #calendar .fc-button-primary:hover {
        background-color: #F3F4F6;
        border-color: #D1D5DB;
        color: #1F2937;
    }

    #calendar .fc-button-primary.fc-button-active {
        background-color: #5C9DC0;
        border-color: #5C9DC0;
        color: #FFFFFF;
    }

    #calendar .fc-button-primary.fc-button-active:hover {
        background-color: #4A8BB0;
        border-color: #4A8BB0;
    }

    /* -------------------------------------------------------------------------
     * Mobile calendar adaptations
     * ------------------------------------------------------------------------- */
    @media (max-width: 767.98px) {
        #calendar .provider-column {
            width: 280px;
            min-width: 280px;
            max-width: 280px;
        }

        #calendar .calendar-view .date-column .provider-column h6 {
            font-size: 0.8rem;
            padding: 6px 8px;
        }

        #calendar .fc-timegrid-slot[data-time$=":00:00"] .fc-timegrid-slot-label-cushion {
            font-size: 0.85rem;
        }

        #calendar .fc-timegrid-slot[data-time$=":15:00"] .fc-timegrid-slot-label-cushion,
        #calendar .fc-timegrid-slot[data-time$=":30:00"] .fc-timegrid-slot-label-cushion,
        #calendar .fc-timegrid-slot[data-time$=":45:00"] .fc-timegrid-slot-label-cushion {
            font-size: 0.65rem;
        }

        #calendar .fc-event-main,
        #calendar .fc-event-main-frame,
        #calendar .fc-event-time,
        #calendar .fc-event-title-container,
        #calendar .fc-event-title {
            font-size: 0.7rem !important;
            line-height: 1.1 !important;
        }
    }
</style>
