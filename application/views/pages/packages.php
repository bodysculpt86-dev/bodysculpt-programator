<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="packages-page">
    <div class="row" id="packages">
        <div id="filter-packages" class="filter-records col col-12 mb-4">
            <button id="add-package" class="btn btn-primary add-record-btn mb-4">
                <i class="fas fa-plus-square me-2"></i>
                <?= lang('add') ?>
            </button>

            <form class="mb-4" id="filter-packages-form">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" class="key form-control" aria-label="keyword" placeholder="<?= lang('search') ?>">

                            <button class="filter btn btn-outline-secondary" type="submit" data-tippy-content="<?= lang('filter') ?>">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select category-filter">
                            <option value=""><?= lang('all_categories') ?></option>
                            <?php foreach (vars('service_categories') as $category): ?>
                                <option value="<?= $category['value'] ?>"><?= e($category['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select active-filter">
                            <option value=""><?= lang('all_statuses') ?></option>
                            <option value="1"><?= lang('active') ?></option>
                            <option value="0"><?= lang('inactive') ?></option>
                        </select>
                    </div>
                </div>
            </form>

            <h4 class="mb-3 fw-light">
                <?= lang('packages') ?>
            </h4>

            <div class="results overflow-auto" style="max-height: 650px;">
                <!-- JS -->
            </div>
        </div>

        <div class="record-details column col-12 mb-4">
            <div class="btn-toolbar mb-4">
                <div class="add-edit-delete-group btn-group">
                    <button id="edit-package" class="btn btn-outline-secondary" disabled="disabled">
                        <i class="fas fa-edit me-2"></i>
                        <?= lang('edit') ?>
                    </button>
                </div>

                <div class="save-cancel-group" style="display:none;">
                    <button id="save-package" class="btn btn-primary">
                        <i class="fas fa-check-square me-2"></i>
                        <?= lang('save') ?>
                    </button>
                    <button id="cancel-package" class="btn btn-outline-secondary">
                        <?= lang('cancel') ?>
                    </button>
                    <button id="delete-package" class="btn btn-outline-danger ms-2">
                        <i class="fas fa-trash-alt me-2"></i>
                        <?= lang('delete') ?>
                    </button>
                </div>
            </div>

            <h4 class="mb-3 fw-light">
                <?= lang('details') ?>
            </h4>

            <div class="form-message alert" style="display:none;"></div>

            <input type="hidden" id="package-id">

            <div class="mb-3">
                <label class="form-label" for="package-name">
                    <?= lang('name') ?>
                    <span class="text-danger" hidden>*</span>
                </label>
                <input id="package-name" class="form-control required" maxlength="256" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label" for="package-price">
                    <?= lang('package_price') ?>
                    <span class="text-danger" hidden>*</span>
                </label>
                <input id="package-price" class="form-control required" type="number" step="0.01" min="0" disabled>
                <div class="form-text text-muted">
                    <small><?= lang('package_price_hint') ?></small>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="package-category">
                    <?= lang('category') ?>
                </label>
                <select id="package-category" class="form-select" disabled>
                    <option value=""><?= lang('no_category') ?></option>
                    <?php foreach (vars('service_categories') as $category): ?>
                        <option value="<?= $category['value'] ?>"><?= e($category['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="package-validity-days">
                    <?= lang('validity_days') ?>
                </label>
                <input id="package-validity-days" class="form-control required" type="number" min="1" disabled>
                <div class="form-text text-muted">
                    <small><?= lang('validity_days_hint') ?></small>
                </div>
            </div>

            <div class="border rounded mb-3 p-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="package-is-active" checked disabled>

                    <label class="form-check-label" for="package-is-active">
                        <?= lang('active') ?>
                    </label>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="package-notes">
                    <?= lang('notes') ?>
                </label>
                <textarea id="package-notes" rows="3" class="form-control" disabled></textarea>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <label class="form-label mb-0">
                    <?= lang('package_items') ?>
                    <span class="text-danger" hidden>*</span>
                </label>
                <button type="button" id="add-package-item" class="btn btn-sm btn-outline-secondary" disabled>
                    <i class="fas fa-plus me-1"></i>
                    <?= lang('add_item') ?>
                </button>
            </div>

            <div id="package-items-container" class="card card-body border mb-3">
                <!-- JS -->
            </div>

            <div class="mb-3">
                <label class="form-label" for="package-calculated-sum">
                    <?= lang('package_calculated_sum') ?>
                </label>
                <input id="package-calculated-sum" class="form-control" type="number" step="0.01" readonly>
            </div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/packages_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/packages.js') ?>"></script>

<?php end_section('scripts'); ?>
