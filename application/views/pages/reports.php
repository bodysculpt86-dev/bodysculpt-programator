<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="reports-page">
    <div class="row">
        <div class="col-12 mb-4">
            <h4 class="mb-3 fw-light">
                <?= lang('revenue_report') ?>
            </h4>

            <p class="text-muted">
                <?= lang('revenue_report_hint') ?>
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-10 col-xl-8 mb-4">
            <?php component('date_range_selector'); ?>
        </div>
    </div>

    <div class="row" id="reports-empty-hint">
        <div class="col-12 col-lg-10 col-xl-8">
            <p class="text-muted"><?= lang('select_range_first') ?></p>
        </div>
    </div>

    <div class="row d-none" id="daily-revenue-section">
        <div class="col-12 col-lg-10 col-xl-8 mb-4">
            <h5 class="fw-light"><?= lang('daily') ?></h5>

            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th><?= lang('date') ?></th>
                            <th class="text-end"><?= lang('total') ?></th>
                        </tr>
                    </thead>
                    <tbody id="daily-revenue-body">
                        <tr>
                            <td colspan="2" class="text-muted text-center">
                                <?= lang('no_records_found') ?>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot id="daily-revenue-foot" class="d-none">
                        <tr class="fw-bold">
                            <td><?= lang('total') ?></td>
                            <td class="text-end" id="daily-revenue-total"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="row d-none" id="monthly-revenue-section">
        <div class="col-12 col-lg-10 col-xl-8 mb-4">
            <h5 class="fw-light"><?= lang('monthly') ?></h5>

            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th><?= lang('month') ?></th>
                            <th class="text-end"><?= lang('total') ?></th>
                        </tr>
                    </thead>
                    <tbody id="monthly-revenue-body">
                        <tr>
                            <td colspan="2" class="text-muted text-center">
                                <?= lang('no_records_found') ?>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot id="monthly-revenue-foot" class="d-none">
                        <tr class="fw-bold">
                            <td><?= lang('total') ?></td>
                            <td class="text-end" id="monthly-revenue-total"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/utils/ui.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/date_range_selector.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/reports.js') ?>"></script>

<?php end_section('scripts'); ?>
