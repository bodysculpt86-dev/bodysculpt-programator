<div class="date-range-selector">
    <div class="btn-group flex-wrap mb-3 w-100" role="group">
        <button type="button" class="btn btn-outline-secondary range-btn" data-range="today">
            <?= lang('today') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary range-btn" data-range="last_7_days">
            <?= lang('last_7_days') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary range-btn" data-range="last_14_days">
            <?= lang('last_14_days') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary range-btn" data-range="last_30_days">
            <?= lang('last_30_days') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary range-btn" data-range="last_365_days">
            <?= lang('last_365_days') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary range-btn" data-range="current_week">
            <?= lang('current_week') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary range-btn" data-range="last_week">
            <?= lang('last_week') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary range-btn" data-range="current_month">
            <?= lang('current_month') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary range-btn" data-range="last_month">
            <?= lang('last_month') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary range-btn" data-range="current_year">
            <?= lang('current_year') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary range-btn" data-range="last_year">
            <?= lang('last_year') ?>
        </button>
    </div>

    <form class="date-range-form row g-3 align-items-end">
        <div class="col-12 col-md-4">
            <label class="form-label">
                <?= lang('start') ?>
            </label>
            <input type="text" class="form-control date-range-start" required>
        </div>

        <div class="col-12 col-md-4">
            <label class="form-label">
                <?= lang('end') ?>
            </label>
            <input type="text" class="form-control date-range-end" required>
        </div>

        <div class="col-12 col-md-4">
            <button type="submit" class="btn btn-primary w-100 date-range-generate">
                <i class="fas fa-sync-alt me-2"></i>
                <?= lang('generate') ?>
            </button>
        </div>
    </form>
</div>
