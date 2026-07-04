<div class="date-range-selector dropdown">
    <button type="button" class="btn btn-outline-secondary dropdown-toggle date-range-toggle"
            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="fas fa-calendar-alt me-2"></i>
        <span class="date-range-label"><?= lang('select_period') ?></span>
    </button>
    <div class="dropdown-menu date-range-menu p-3" style="min-width: 260px;">
        <div class="d-grid gap-2 mb-3">
            <button type="button" class="btn btn-sm btn-outline-secondary range-btn" data-range="today"><?= lang('today') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary range-btn" data-range="last_7_days"><?= lang('last_7_days') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary range-btn" data-range="last_14_days"><?= lang('last_14_days') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary range-btn" data-range="last_30_days"><?= lang('last_30_days') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary range-btn" data-range="last_365_days"><?= lang('last_365_days') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary range-btn" data-range="current_week"><?= lang('current_week') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary range-btn" data-range="last_week"><?= lang('last_week') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary range-btn" data-range="current_month"><?= lang('current_month') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary range-btn" data-range="last_month"><?= lang('last_month') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary range-btn" data-range="current_year"><?= lang('current_year') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary range-btn" data-range="last_year"><?= lang('last_year') ?></button>
        </div>
        <div class="dropdown-divider"></div>
        <form class="date-range-form">
            <div class="mb-2">
                <label class="form-label mb-1"><?= lang('start') ?></label>
                <input type="text" class="form-control form-control-sm date-range-start" required>
            </div>
            <div class="mb-3">
                <label class="form-label mb-1"><?= lang('end') ?></label>
                <input type="text" class="form-control form-control-sm date-range-end" required>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1 date-range-generate"><?= lang('generate') ?></button>
                <button type="button" class="btn btn-sm btn-outline-secondary date-range-cancel" data-bs-toggle="dropdown"><?= lang('cancel') ?></button>
            </div>
        </form>
    </div>
</div>
