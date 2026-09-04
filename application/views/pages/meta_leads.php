<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="meta-leads-page">
    <div class="row">
        <div class="col-12 mb-4">
            <h4 class="mb-3 fw-light">
                <?= lang('meta_leads') ?>
            </h4>

            <p class="text-muted">
                <?= lang('meta_leads_hint') ?>
            </p>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12 col-md-3 mb-2 mb-md-0">
            <select id="meta-leads-status-filter" class="form-select">
                <option value=""><?= lang('meta_leads_all') ?></option>
                <option value="new"><?= lang('meta_leads_status_new') ?></option>
                <option value="converted"><?= lang('meta_leads_status_converted') ?></option>
            </select>
        </div>
        <div class="col-12 col-md-4">
            <div class="input-group">
                <input type="text" id="meta-leads-keyword" class="form-control"
                       placeholder="<?= lang('type_to_filter_customers') ?>">
                <button type="button" id="meta-leads-filter" class="btn btn-outline-secondary">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th><?= lang('meta_leads_name') ?></th>
                    <th><?= lang('phone_number') ?></th>
                    <th><?= lang('email') ?></th>
                    <th><?= lang('meta_leads_received_at') ?></th>
                    <th><?= lang('status') ?></th>
                    <th><?= lang('meta_leads_actions') ?></th>
                </tr>
            </thead>
            <tbody id="meta-leads-results-body"></tbody>
        </table>
    </div>

    <div id="meta-leads-empty" class="text-muted d-none">
        <?= lang('no_records_found') ?>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/meta_leads_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/meta_leads.js') ?>"></script>

<?php end_section('scripts'); ?>
