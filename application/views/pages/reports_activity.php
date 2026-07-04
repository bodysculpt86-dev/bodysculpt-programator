<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="reports-activity-page">
    <div class="row">
        <div class="col-12 mb-4">
            <h4 class="mb-3 fw-light">
                <?= lang('activity_matrix_report') ?>
            </h4>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <?php component('date_range_selector'); ?>

        <div class="dropdown payment-filter-dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                    id="payment-filter-toggle" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside" aria-expanded="false">
                <?= lang('payment_type') ?>
            </button>
            <div class="dropdown-menu payment-filter-dropdown-menu" aria-labelledby="payment-filter-toggle">
                <div class="dropdown-item">
                    <div class="form-check">
                        <input class="form-check-input payment-filter-select-all" type="checkbox" id="payment-filter-select-all" checked>
                        <label class="form-check-label" for="payment-filter-select-all"><?= lang('select_all') ?></label>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <?php foreach (vars('payment_options') as $status): ?>
                    <div class="dropdown-item">
                        <div class="form-check">
                            <input class="form-check-input payment-filter" type="checkbox"
                                   value="<?= e($status) ?>" id="payment-filter-<?= md5($status) ?>" checked>
                            <label class="form-check-label" for="payment-filter-<?= md5($status) ?>"><?= e($status) ?></label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <select id="group-by-select" class="form-select" style="width: auto;">
            <option value="month"><?= lang('group_by_month') ?></option>
            <option value="category"><?= lang('group_by_category') ?></option>
            <option value="service"><?= lang('group_by_service') ?></option>
        </select>
    </div>

    <div class="row" id="reports-empty-hint">
        <div class="col-12">
            <p class="text-muted"><?= lang('select_range_first') ?></p>
        </div>
    </div>

    <div class="row d-none" id="activity-matrix-section">
        <div class="col-12">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="activity-matrix-table">
                    <thead id="activity-head">
                        <tr>
                            <th rowspan="2" class="align-middle" id="matrix-row-header"><?= lang('month') ?></th>
                            <?php foreach (vars('providers') as $provider): ?>
                                <th colspan="2" class="text-center" data-provider-id="<?= (int) $provider['id'] ?>">
                                    <?= e($provider['first_name'] . ' ' . $provider['last_name']) ?>
                                </th>
                            <?php endforeach; ?>
                            <th colspan="2" class="text-center" data-provider-id="total"><?= lang('total') ?></th>
                        </tr>
                        <tr>
                            <?php foreach (vars('providers') as $provider): ?>
                                <th class="text-center" data-provider-id="<?= (int) $provider['id'] ?>" data-type="count">
                                    <i class="fas fa-calendar"></i>
                                </th>
                                <th class="text-center" data-provider-id="<?= (int) $provider['id'] ?>" data-type="total">
                                    <i class="fas fa-list"></i>
                                </th>
                            <?php endforeach; ?>
                            <th class="text-center" data-provider-id="total" data-type="count">
                                <i class="fas fa-calendar"></i>
                            </th>
                            <th class="text-center" data-provider-id="total" data-type="total">
                                <i class="fas fa-list"></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="activity-matrix-body">
                        <tr>
                            <td colspan="<?= 1 + count(vars('providers')) * 2 + 2 ?>" class="text-muted text-center">
                                <?= lang('no_records_found') ?>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot id="activity-matrix-foot">
                        <tr class="fw-bold">
                            <td><?= lang('total') ?></td>
                            <?php foreach (vars('providers') as $provider): ?>
                                <td class="text-center" data-provider-id="<?= (int) $provider['id'] ?>" data-type="count">-</td>
                                <td class="text-end" data-provider-id="<?= (int) $provider['id'] ?>" data-type="total">-</td>
                            <?php endforeach; ?>
                            <td class="text-center" data-provider-id="total" data-type="count">-</td>
                            <td class="text-end" data-provider-id="total" data-type="total">-</td>
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
<script src="<?= asset_url('assets/js/pages/reports_activity.js') ?>"></script>

<?php end_section('scripts'); ?>
