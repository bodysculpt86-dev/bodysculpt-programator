<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="customer-packages-page">
    <div class="row" id="customer-packages">
        <div id="filter-customer-packages" class="filter-records col col-12 mb-4">
            <button id="sell-customer-package" class="btn btn-primary add-record-btn mb-4">
                <i class="fas fa-plus-square me-2"></i>
                <?= lang('sell_package') ?>
            </button>

            <form class="mb-4" id="filter-customer-packages-form">
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" class="key form-control" aria-label="keyword" placeholder="<?= lang('search') ?>">

                            <button class="filter btn btn-outline-secondary" type="submit" data-tippy-content="<?= lang('filter') ?>">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select active-filter">
                            <option value=""><?= lang('all_statuses') ?></option>
                            <option value="1"><?= lang('active') ?></option>
                            <option value="0"><?= lang('inactive') ?></option>
                        </select>
                    </div>
                </div>
            </form>

            <h4 class="mb-3 fw-light">
                <?= lang('customer_packages') ?>
            </h4>

            <div class="results overflow-auto" style="max-height: 650px;">
                <!-- JS -->
            </div>
        </div>

        <div class="record-details column col-12 mb-4">
            <div class="btn-toolbar mb-4">
                <div class="add-edit-delete-group btn-group">
                    <button id="edit-customer-package" class="btn btn-outline-secondary" disabled="disabled">
                        <i class="fas fa-edit me-2"></i>
                        <?= lang('edit') ?>
                    </button>
                </div>

                <div class="save-cancel-group" style="display:none;">
                    <button id="save-customer-package" class="btn btn-primary">
                        <i class="fas fa-check-square me-2"></i>
                        <?= lang('save') ?>
                    </button>
                    <button id="cancel-customer-package" class="btn btn-outline-secondary">
                        <?= lang('cancel') ?>
                    </button>
                    <button id="delete-customer-package" class="btn btn-outline-danger ms-2">
                        <i class="fas fa-trash-alt me-2"></i>
                        <?= lang('delete') ?>
                    </button>
                </div>
            </div>

            <h4 class="mb-3 fw-light">
                <?= lang('details') ?>
            </h4>

            <div class="form-message alert" style="display:none;"></div>

            <input type="hidden" id="customer-package-id">

            <div class="mb-3">
                <label class="form-label" for="customer-package-customer">
                    <?= lang('customer') ?>
                </label>
                <input id="customer-package-customer" class="form-control" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label" for="customer-package-package">
                    <?= lang('package') ?>
                </label>
                <input id="customer-package-package" class="form-control" disabled>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="customer-package-purchase-date">
                        <?= lang('purchase_date') ?>
                    </label>
                    <input id="customer-package-purchase-date" class="form-control" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="customer-package-expiry-date">
                        <?= lang('expiry_date') ?>
                    </label>
                    <input id="customer-package-expiry-date" class="form-control" disabled>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="customer-package-status">
                    <?= lang('status') ?>
                </label>
                <input id="customer-package-status" class="form-control" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label" for="customer-package-notes">
                    <?= lang('notes') ?>
                </label>
                <textarea id="customer-package-notes" rows="2" class="form-control" disabled></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <?= lang('package_items') ?>
                    <span class="text-danger" hidden>*</span>
                </label>
                <div id="customer-package-items-container" class="card card-body border">
                    <!-- JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sell-customer-package-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= lang('sell_package') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('close') ?>"></button>
            </div>
            <div class="modal-body">
                <div class="form-message alert" style="display:none;"></div>

                <input type="hidden" id="sell-customer-id">

                <div class="mb-3">
                    <label class="form-label" for="sell-customer-keyword">
                        <?= lang('customer') ?>
                        <span class="text-danger">*</span>
                    </label>
                    <input id="sell-customer-keyword" class="form-control" autocomplete="off" placeholder="<?= lang('search_customer_placeholder') ?>">
                    <div id="sell-customer-results" class="list-group mt-1" style="max-height: 200px; overflow-y: auto;">
                        <!-- JS -->
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="sell-package-id">
                        <?= lang('package') ?>
                        <span class="text-danger">*</span>
                    </label>
                    <select id="sell-package-id" class="form-select">
                        <option value=""></option>
                        <?php foreach (vars('available_packages') as $package): ?>
                            <option value="<?= $package['value'] ?>"><?= e($package['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="sell-notes">
                        <?= lang('notes') ?>
                    </label>
                    <textarea id="sell-notes" rows="2" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <?= lang('cancel') ?>
                </button>
                <button type="button" id="confirm-sell-customer-package" class="btn btn-primary">
                    <?= lang('sell') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/customers_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/http/customer_packages_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/customer_packages.js') ?>"></script>

<?php end_section('scripts'); ?>
