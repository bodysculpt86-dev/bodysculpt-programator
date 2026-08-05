<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="customers-page">
    <div class="row" id="customers">
        <div id="filter-customers" class="filter-records col col-12 mb-4">
            <?php if (
                can('add', PRIV_CUSTOMERS) &&
                (!setting('limit_customer_access') || vars('role_slug') === DB_SLUG_ADMIN)
            ): ?>
                <button id="add-customer" class="btn btn-primary add-record-btn mb-4">
                    <i class="fas fa-plus-square me-2"></i>
                    <?= lang('add') ?>
                </button>

                <button id="import-customers" class="btn btn-outline-secondary mb-4" type="button"
                        data-bs-toggle="modal" data-bs-target="#import-customers-modal">
                    <i class="fas fa-file-import me-2"></i>
                    <?= lang('import_customers') ?>
                </button>

                <a href="<?= site_url('customers/import_template') ?>" class="btn btn-outline-secondary mb-4"
                   download>
                    <i class="fas fa-download me-2"></i>
                    <?= lang('download_template') ?>
                </a>
            <?php endif; ?>

            <?php if (can('view', PRIV_CUSTOMERS)): ?>
                <a href="<?= site_url('customers/export') ?>" class="btn btn-outline-secondary mb-4" download>
                    <i class="fas fa-file-export me-2"></i>
                    <?= lang('export_customers') ?>
                </a>
            <?php endif; ?>

            <form class="mb-4">
                <div class="input-group mb-3">
                    <input type="text" class="key form-control" aria-label="keyword">

                    <button class="filter btn btn-outline-secondary" type="submit"
                            data-tippy-content="<?= lang('filter') ?>">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>

            <h4 class="mb-3 fw-light">
                <?= lang('customers') ?>
            </h4>

            <div class="results overflow-auto" style="max-height: 650px;">
                <!-- JS -->
            </div>
        </div>

        <div class="record-details col-12 mb-4">
            <div class="btn-toolbar mb-4">
                <div id="add-edit-delete-group" class="btn-group">

                    <?php if (can('edit', PRIV_CUSTOMERS)): ?>
                        <button id="edit-customer" class="btn btn-outline-secondary" disabled="disabled">
                            <i class="fas fa-edit me-2"></i>
                            <?= lang('edit') ?>
                        </button>
                    <?php endif; ?>
                </div>

                <div id="save-cancel-group" style="display:none;">
                    <button id="save-customer" class="btn btn-primary">
                        <i class="fas fa-check-square me-2"></i>
                        <?= lang('save') ?>
                    </button>
                    <button id="cancel-customer" class="btn btn-outline-secondary">
                        <?= lang('cancel') ?>
                    </button>
                    <?php if (can('delete', PRIV_CUSTOMERS)): ?>
                        <button id="delete-customer" class="btn btn-outline-danger ms-2">
                            <i class="fas fa-trash-alt me-2"></i>
                            <?= lang('delete') ?>
                        </button>
                    <?php endif; ?>
                </div>

            </div>

            <input id="customer-id" type="hidden">

            <div class="row">
                <div class="col-12 col-lg-6" style="margin-left: 0;">
                    <h4 class="mb-3 fw-light">
                        <?= lang('details') ?>
                    </h4>

                    <div id="form-message" class="alert" style="display:none;"></div>

                    <div class="mb-3">
                        <label for="first-name" class="form-label">
                            <?= lang('first_name') ?>
                            <?php if (vars('require_first_name')): ?>
                                <span class="text-danger" hidden>*</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="first-name"
                               class="<?= vars('require_first_name') ? 'required' : '' ?> form-control" maxlength="100"
                               disabled/>
                    </div>

                    <div class="mb-3">
                        <label for="last-name" class="form-label">
                            <?= lang('last_name') ?>
                            <?php if (vars('require_last_name')): ?>
                                <span class="text-danger" hidden>*</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="last-name"
                               class="<?= vars('require_last_name') ? 'required' : '' ?> form-control" maxlength="120"
                               disabled/>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <?= lang('email') ?>
                            <?php if (vars('require_email')): ?>
                                <span class="text-danger" hidden>*</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="email"
                               class="<?= vars('require_email') ? 'required' : '' ?> form-control" maxlength="120"
                               disabled/>
                    </div>

                    <div class="mb-3">
                        <label for="phone-number" class="form-label">
                            <?= lang('phone_number') ?>
                            <?php if (vars('require_phone_number')): ?>
                                <span class="text-danger" hidden>*</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="phone-number" maxlength="60"
                               class="<?= vars('require_phone_number') ? 'required' : '' ?> form-control" disabled/>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">
                            <?= lang('address') ?>
                            <?php if (vars('require_address')): ?>
                                <span class="text-danger" hidden>*</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="address"
                               class="<?= vars('require_address') ? 'required' : '' ?> form-control"
                               maxlength="120" disabled/>
                    </div>

                    <div class="mb-3">
                        <label for="city" class="form-label">
                            <?= lang('city') ?>
                            <?php if (vars('require_city')): ?>
                                <span class="text-danger" hidden>*</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="city" class="<?= vars('require_city') ? 'required' : '' ?> form-control"
                               maxlength="120" disabled/>
                    </div>

                    <div class="mb-3">
                        <label for="state" class="form-label">
                            <?= lang('county') ?>
                        </label>
                        <input type="text" id="state" class="form-control" maxlength="120" disabled/>
                    </div>

                    <div class="mb-3">
                        <label for="zip-code" class="form-label">
                            <?= lang('zip_code') ?>
                            <?php if (vars('require_zip_code')): ?>
                                <span class="text-danger" hidden>*</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="zip-code"
                               class="<?= vars('require_zip_code') ? 'required' : '' ?> form-control"
                               maxlength="120" disabled/>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="language">
                            <?= lang('language') ?>
                            <span class="text-danger" hidden>*</span>
                        </label>
                        <select id="language" class="form-select required" disabled>
                            <?php foreach (vars('available_languages') as $available_language): ?>
                                <option value="<?= $available_language ?>">
                                    <?= ucfirst($available_language) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (setting('ldap_is_active')): ?>
                        <div class="mb-3">
                            <label for="ldap-dn" class="form-label">
                                <?= lang('ldap_dn') ?>
                            </label>
                            <input type="text" id="ldap-dn" class="form-control" maxlength="100" disabled/>
                        </div>
                    <?php endif; ?>

                    <?php component('custom_fields', [
                        'disabled' => true,
                    ]); ?>

                    <div class="mb-3">
                        <label class="form-label" for="notes">
                            <?= lang('notes') ?>
                        </label>
                        <textarea id="notes" rows="4" class="form-control" disabled></textarea>
                    </div>

                </div>

                <div class="col-12 col-lg-6">
                    <h4 class="mb-3 fw-light">
                        <?= lang('appointments') ?>
                    </h4>

                    <div id="customer-appointments" class="card border p-3 overflow-auto mb-4" style="min-height: 400px; max-height: 800px; max-width: 330px; width: 100%;"></div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php if (can('add', PRIV_CUSTOMERS)): ?>
    <div class="modal fade" id="import-customers-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= lang('import_customers') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="import-customers-form" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= vars('csrf_token') ?>">

                        <div class="mb-3">
                            <label for="import-file" class="form-label">
                                <?= lang('select_file') ?> (.xls, .xlsx)
                            </label>
                            <input type="file" id="import-file" name="import_file" class="form-control"
                                   accept=".xls,.xlsx" required>
                        </div>
                    </form>

                    <div id="import-result" class="alert d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <?= lang('close') ?>
                    </button>
                    <button type="button" id="start-import" class="btn btn-primary">
                        <i class="fas fa-file-import me-2"></i>
                        <?= lang('import') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/customers_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/customers.js') ?>"></script>

<?php end_section('scripts'); ?>
