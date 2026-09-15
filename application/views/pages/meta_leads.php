<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="meta-leads-page">
    <div class="row">
        <div class="col-12 mb-3">
            <h4 class="mb-2 fw-light">
                <?= lang('meta_leads') ?>
            </h4>

            <p class="text-muted mb-0">
                <?= lang('meta_leads_hint') ?>
            </p>
        </div>
    </div>

    <div class="row mb-3 align-items-center g-2">
        <div class="col-12 col-md-auto">
            <div class="btn-group flex-wrap" role="group" id="meta-leads-call-filter">
                <button type="button" class="btn btn-outline-primary" data-call-status="de sunat">
                    <?= lang('call_status_de_sunat') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-call-status="nu a raspuns">
                    <?= lang('call_status_nu_a_raspuns') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-call-status="revine">
                    <?= lang('call_status_revine') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-call-status="nu e interesat">
                    <?= lang('call_status_nu_e_interesat') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-call-status="">
                    <?= lang('meta_leads_all') ?>
                </button>
            </div>
        </div>

        <div class="col-12 col-md-4 ms-md-auto">
            <div class="input-group">
                <input type="text" id="meta-leads-keyword" class="form-control"
                       placeholder="<?= lang('type_to_filter_meta_leads') ?>">
                <button type="button" id="meta-leads-filter" class="btn btn-outline-secondary">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Desktop: table -->
    <div class="table-responsive d-none d-md-block">
        <table class="table table-sm table-hover align-middle">
            <thead>
                <tr>
                    <th><?= lang('meta_leads_name') ?></th>
                    <th><?= lang('meta_leads_form_answers') ?></th>
                    <th><?= lang('meta_leads_received_at') ?></th>
                    <th><?= lang('call_status') ?></th>
                    <th><?= lang('assigned_to') ?></th>
                    <th><?= lang('call_note') ?></th>
                    <th><?= lang('meta_leads_actions') ?></th>
                </tr>
            </thead>
            <tbody id="meta-leads-table-body"></tbody>
        </table>
    </div>

    <!-- Mobile: cards -->
    <div id="meta-leads-cards" class="d-md-none"></div>

    <div id="meta-leads-empty" class="text-muted d-none">
        <?= lang('no_records_found') ?>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/meta_leads_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/meta_leads.js') ?>"></script>

<?php end_section('scripts'); ?>
