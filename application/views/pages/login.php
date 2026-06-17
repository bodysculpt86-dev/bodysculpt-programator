<?php extend('layouts/account_layout'); ?>

<?php section('content'); ?>

<div class="text-center mb-4">
    <img src="<?= asset_url('assets/img/logo.png') ?>" 
         alt="Bookings by Revclar" class="shadow mb-3" width="72" height="72">
    <h4 class="text-primary fw-semibold mb-1"><?= lang('login') ?></h4>
    <p class=" small mb-0"><?= lang('you_need_to_login') ?></p>
</div>

<div class="alert d-none"></div>

<form id="login-form">
    <div class="mb-3">
        <label for="username" class="form-label fw-medium ">
            <?= lang('username') ?>
        </label>
        <div class="input-group">
            <span class="input-group-text border-end-0">
                <i class="fas fa-user "></i>
            </span>
            <input type="text" id="username" 
                   placeholder="<?= lang('enter_username_here') ?>" class="form-control border-start-0 ps-2" required/>
        </div>
    </div>

    <div class="mb-4">
        <label for="password" class="form-label fw-medium ">
            <?= lang('password') ?>
        </label>
        <div class="input-group">
            <span class="input-group-text border-end-0">
                <i class="fas fa-lock "></i>
            </span>
            <input type="password" id="password" 
                   placeholder="<?= lang('enter_password_here') ?>" class="form-control border-start-0 ps-2" required/>
        </div>
    </div>

    <?php if (vars('require_captcha')): ?>
        <?php if (vars('altcha_enabled') === '1'): ?>
            <div class="mb-4">
                <div id="altcha-widget" class="altcha-widget"></div>
                <input type="hidden" id="altcha-payload" value="">
                <span id="altcha-hint" class="help-block text-danger small" style="opacity:0">&nbsp;</span>
            </div>
        <?php else: ?>
            <div class="mb-4">
                <label class="captcha-title form-label fw-medium" for="captcha-text">
                    CAPTCHA
                    <button type="button" class="btn btn-link text-dark text-decoration-none py-0 px-1">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </label>
                <img class="captcha-image d-block mb-2 rounded" src="<?= site_url('captcha') ?>" alt="CAPTCHA">
                <input id="captcha-text" class="captcha-text form-control" type="text" placeholder="<?= lang('enter_captcha_here') ?>"/>
                <span id="captcha-hint" class="help-block text-danger small" style="opacity:0">&nbsp;</span>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="d-grid gap-2 mb-3">
        <button type="submit" id="login" class="btn btn-primary">
            <i class="fas fa-sign-in-alt me-2"></i>
            <?= lang('login') ?>
        </button>
    </div>

    <div class="text-center">
        <a href="<?= site_url('recovery') ?>" class="text-decoration-none  small">
            <i class="fas fa-key me-1"></i>
            <?= lang('forgot_your_password') ?>
        </a>
    </div>
</form>
<?php end_section('content'); ?>

<?php section('styles'); ?>
<style>
    body {
        background: #09090b;
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    .card {
        background: #18181b;
        border: 1px solid #27272a;
        border-radius: 0.75rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }

    .card-footer {
        background: #18181b;
        border-top: 1px solid #27272a;
        color: #a1a1aa;
    }

    h4,
    .form-label,
    .text-primary {
        color: #fafafa !important;
    }

    .form-control,
    .input-group-text {
        background: #27272a;
        border-color: #3f3f46;
        color: #fafafa;
    }

    .form-control::placeholder {
        color: #a1a1aa;
    }

    .form-control:focus,
    .input-group:focus-within .form-control,
    .input-group:focus-within .input-group-text {
        background: #27272a;
        border-color: #6366f1;
        color: #fafafa;
    }

    .form-control:focus {
        box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
    }

    .input-group-text {
        color: #a1a1aa;
    }

    .btn-primary,
    .btn-primary:focus {
        background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
        border: none !important;
        color: #fff !important;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #4f46e5, #7c3aed) !important;
    }

    a {
        color: #a5b4fc;
    }

    a:hover {
        color: #c7d2fe;
    }
</style>
<?php end_section('styles'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/login_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/login.js') ?>"></script>

<?php end_section('scripts'); ?>
