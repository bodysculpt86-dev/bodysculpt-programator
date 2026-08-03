<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="invoices-page">
    <div class="row" id="invoices">
        <div class="col col-12 mb-4">
            <button id="issue-invoice" class="btn btn-primary mb-4">
                <i class="fas fa-file-invoice me-2"></i>
                <?= lang('issue_invoice') ?>
            </button>

            <h4 class="mb-3 fw-light">
                <?= lang('invoices') ?>
            </h4>

            <div class="invoice-results overflow-auto" style="max-height: 650px;">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th><?= lang('date') ?></th>
                            <th><?= lang('client') ?></th>
                            <th><?= lang('invoice_number') ?></th>
                            <th><?= lang('total') ?></th>
                            <th><?= lang('status') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="invoices-history">
                        <!-- JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="issue-invoice-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?= lang('generate_fiscal_invoice') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Phase B: billing client (search or create PF/PJ) -->

                <input type="hidden" id="client-id">

                <div id="selected-client" class="alert alert-secondary d-none d-flex justify-content-between align-items-center">
                    <span id="selected-client-text"></span>
                    <button type="button" id="change-client" class="btn btn-sm btn-outline-secondary">
                        <?= lang('change_client') ?>
                    </button>
                </div>

                <div id="client-picker">
                    <div class="mb-3">
                        <label class="form-label" for="client-search">
                            <?= lang('search_client') ?>
                        </label>
                        <input id="client-search" class="form-control" autocomplete="off" placeholder="<?= lang('search') ?>">
                        <div id="client-search-results" class="list-group mt-2"></div>
                    </div>

                    <button type="button" id="new-client-toggle" class="btn btn-outline-secondary mb-3">
                        <i class="fas fa-plus-square me-2"></i>
                        <?= lang('new_client') ?>
                    </button>
                </div>

                <div id="new-client-form" class="d-none">
                    <div class="mb-3">
                        <label class="form-label d-block"><?= lang('client_type') ?></label>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="client-type" id="client-type-pf" value="pf" checked>
                            <label class="btn btn-outline-secondary" for="client-type-pf">
                                <?= lang('persoana_fizica') ?>
                            </label>
                            <input type="radio" class="btn-check" name="client-type" id="client-type-pj" value="pj">
                            <label class="btn btn-outline-secondary" for="client-type-pj">
                                <?= lang('persoana_juridica') ?>
                            </label>
                        </div>
                    </div>

                    <div class="pj-only d-none mb-3">
                        <label class="form-label" for="client-cui"><?= lang('cui') ?></label>
                        <div class="input-group">
                            <input id="client-cui" class="form-control" maxlength="20">
                            <button type="button" id="lookup-cui" class="btn btn-outline-secondary">
                                <i class="fas fa-search me-2"></i>
                                <?= lang('lookup_anaf') ?>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="client-name"><?= lang('name') ?></label>
                        <input id="client-name" class="form-control" maxlength="256">
                    </div>

                    <div class="pj-only d-none mb-3">
                        <label class="form-label" for="client-reg-com"><?= lang('reg_com') ?></label>
                        <input id="client-reg-com" class="form-control" maxlength="64">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="client-address"><?= lang('address') ?></label>
                        <input id="client-address" class="form-control" maxlength="512">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="client-city"><?= lang('city') ?></label>
                            <input id="client-city" class="form-control" maxlength="128">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="client-county"><?= lang('county') ?></label>
                            <input id="client-county" class="form-control" maxlength="128">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="client-email"><?= lang('email') ?></label>
                            <input id="client-email" class="form-control" maxlength="128">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="client-phone"><?= lang('phone_number') ?></label>
                            <input id="client-phone" class="form-control" maxlength="64">
                        </div>
                    </div>

                    <button type="button" id="save-client" class="btn btn-primary">
                        <i class="fas fa-check-square me-2"></i>
                        <?= lang('save') ?>
                    </button>
                </div>

                <div id="client-form-message" class="alert mt-3 d-none"></div>

                <hr>

                <!-- Phase C: invoice lines (Servicii/Pachete) + totals -->

                <ul class="nav nav-pills mb-3" id="line-source-tabs">
                    <li class="nav-item">
                        <button type="button" class="nav-link active" data-source="service">
                            <?= lang('services') ?>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" data-source="package">
                            <?= lang('packages') ?>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link disabled" disabled
                                data-tippy-content="<?= lang('coming_soon') ?>">
                            <?= lang('products') ?>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" data-source="manual">
                            <?= lang('manual_line') ?>
                        </button>
                    </li>
                </ul>

                <div id="line-picker-catalog" class="row g-2 mb-3">
                    <div class="col-md-9">
                        <select id="line-catalog-select" class="form-select"></select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" id="add-catalog-line" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-plus me-1"></i>
                            <?= lang('add') ?>
                        </button>
                    </div>
                </div>

                <div id="line-picker-manual" class="row g-2 mb-3 d-none">
                    <div class="col-md-6">
                        <input id="manual-line-description" class="form-control" maxlength="256"
                               placeholder="<?= lang('description') ?>">
                    </div>
                    <div class="col-md-3">
                        <input id="manual-line-price" class="form-control" type="number" step="0.01" min="0"
                               placeholder="<?= lang('price') ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="button" id="add-manual-line" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-plus me-1"></i>
                            <?= lang('add') ?>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="invoice-lines-table">
                        <thead>
                            <tr>
                                <th><?= lang('description') ?></th>
                                <th style="width: 80px;"><?= lang('qty') ?></th>
                                <th style="width: 110px;"><?= lang('unit_price') ?></th>
                                <th style="width: 90px;"><?= lang('vat_rate') ?></th>
                                <th style="width: 110px;"><?= lang('total') ?></th>
                                <th style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="invoice-lines">
                            <!-- JS -->
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-column align-items-end">
                    <div><?= lang('subtotal') ?>: <strong id="invoice-subtotal">0.00</strong></div>
                    <div><?= lang('vat') ?>: <strong id="invoice-vat">0.00</strong></div>
                    <div class="fs-5 mt-1"><?= lang('total') ?>: <strong id="invoice-total">0.00</strong> <span id="invoice-currency"></span></div>
                </div>
            </div>

            <div class="modal-footer d-flex flex-wrap align-items-center gap-2">
                <div>
                    <label class="form-label mb-0 me-2" for="invoice-issue-date"><?= lang('issue_date') ?></label>
                    <input type="date" id="invoice-issue-date" class="form-control d-inline-block" style="width: auto;">
                </div>

                <div>
                    <label class="form-label mb-0 me-2" for="invoice-payment-method"><?= lang('payment_method') ?></label>
                    <select id="invoice-payment-method" class="form-select d-inline-block" style="width: auto;">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="transfer"><?= lang('bank_transfer') ?></option>
                    </select>
                </div>

                <div class="form-check form-switch ms-2">
                    <input class="form-check-input" type="checkbox" id="invoice-is-draft" checked>
                    <label class="form-check-label" for="invoice-is-draft" data-tippy-content="<?= lang('is_draft_hint') ?>">
                        <?= lang('is_draft_mode') ?>
                    </label>
                </div>

                <button type="button" id="issue-invoice-submit" class="btn btn-primary ms-auto">
                    <i class="fas fa-file-invoice me-2"></i>
                    <?= lang('generate_fiscal_invoice') ?>
                </button>

                <div id="issue-message" class="alert d-none w-100 mb-0 mt-2"></div>
            </div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/invoices_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/invoices.js') ?>"></script>

<?php end_section('scripts'); ?>
