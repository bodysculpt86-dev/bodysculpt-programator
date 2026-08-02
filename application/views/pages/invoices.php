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
                <!-- JS (invoice history - Phase D) -->
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
                <!-- Phase B/C: date, payment method, billing client, line items -->
                <p class="text-muted mb-0">
                    <?= lang('invoice_feature_coming_soon') ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/pages/invoices.js') ?>"></script>

<?php end_section('scripts'); ?>
