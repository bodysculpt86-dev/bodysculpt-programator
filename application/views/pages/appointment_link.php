<?php extend('layouts/message_layout'); ?>

<?php section('content'); ?>

<?php
$appointment = vars('appointment');
$service = $appointment['service'] ?? [];
$provider = $appointment['provider'] ?? [];
$customer = $appointment['customer'] ?? [];
$providerName = trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''));
$customerName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
$token = $appointment['confirmation_token'] ?? '';
?>

<div class="text-start">
    <h4 class="mb-4 text-center"><?= lang('appointment_confirmation') ?></h4>

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <h6 class="card-subtitle mb-2 text-muted"><?= lang('appointment_details_title') ?></h6>
            <ul class="list-unstyled mb-0">
                <li class="mb-1">
                    <strong><?= lang('service') ?>:</strong>
                    <?= e($service['name'] ?? '-') ?>
                </li>
                <li class="mb-1">
                    <strong><?= lang('provider') ?>:</strong>
                    <?= e($providerName ?: '-') ?>
                </li>
                <?php if ($customerName): ?>
                    <li class="mb-1">
                        <strong><?= lang('customer') ?>:</strong>
                        <?= e($customerName) ?>
                    </li>
                <?php endif; ?>
                <li class="mb-1">
                    <strong><?= lang('start') ?>:</strong>
                    <?= e($appointment['start_datetime'] ?? '-') ?>
                </li>
                <li>
                    <strong><?= lang('end') ?>:</strong>
                    <?= e($appointment['end_datetime'] ?? '-') ?>
                </li>
            </ul>
        </div>
    </div>

    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
        <form method="post" action="<?= base_url('p/' . $token . '/confirm') ?>" class="flex-fill">
            <input type="hidden" name="csrf_token" value="<?= e(vars('csrf_token')) ?>">
            <button type="submit" class="btn btn-primary w-100">
                <?= lang('confirm') ?>
            </button>
        </form>

        <form method="post" action="<?= base_url('p/' . $token . '/cancel') ?>" class="flex-fill">
            <input type="hidden" name="csrf_token" value="<?= e(vars('csrf_token')) ?>">
            <button type="submit" class="btn btn-danger w-100">
                <?= lang('cancel') ?>
            </button>
        </form>
    </div>
</div>

<?php end_section('content'); ?>
