<?php extend('layouts/backend_layout'); ?>

<?php section('styles'); ?>

<style>
    #marketing-page .wa-preview {
        max-width: 340px;
        background-color: #e5ddd5;
        border-radius: 12px;
        padding: 24px 16px;
    }

    #marketing-page .wa-bubble {
        background-color: #ffffff;
        color: #212529;
        border-radius: 8px;
        padding: 10px 12px;
        box-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
        white-space: pre-line;
        font-size: 0.9rem;
        line-height: 1.45;
    }

    #marketing-page .wa-placeholder {
        color: #b0b0b0;
    }
</style>

<?php end_section('styles'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="marketing-page">
    <div class="row">
        <div class="col-12 mb-4">
            <h4 class="mb-3 fw-light">
                <?= lang('marketing') ?>
            </h4>

            <p class="text-muted">
                <?= lang('marketing_hint') ?>
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title fw-light mb-3"><?= lang('new_campaign') ?></h5>

                    <div class="mb-3">
                        <label for="campaign-procedure" class="form-label"><?= lang('campaign_procedure') ?></label>
                        <input type="text" id="campaign-procedure" class="form-control"
                               placeholder="<?= lang('campaign_procedure_placeholder') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="campaign-discount" class="form-label"><?= lang('campaign_discount') ?></label>
                        <div class="input-group">
                            <input type="number" id="campaign-discount" class="form-control" min="1" max="100" step="1">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="campaign-valid-until" class="form-label"><?= lang('campaign_valid_until') ?></label>
                        <input type="date" id="campaign-valid-until" class="form-control">
                    </div>

                    <p class="text-muted mb-3">
                        <?= lang('marketing_recipients_hint') ?>
                        <strong id="recipients-count">…</strong>
                    </p>

                    <div class="mb-3">
                        <label for="test-phone" class="form-label"><?= lang('marketing_test_phone') ?></label>
                        <div class="input-group">
                            <input type="tel" id="test-phone" class="form-control"
                                   placeholder="<?= lang('marketing_test_phone_placeholder') ?>">
                            <button type="button" id="test-button" class="btn btn-outline-success">
                                <i class="fas fa-vial me-1"></i><?= lang('marketing_send_test') ?>
                            </button>
                        </div>
                        <div id="test-result" class="form-text d-none"></div>
                    </div>

                    <hr>

                    <button type="button" id="send-button" class="btn btn-success">
                        <i class="fab fa-whatsapp me-2"></i><?= lang('send_to_all') ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6 mb-4">
            <h5 class="fw-light mb-3"><?= lang('message_preview') ?></h5>

            <div class="wa-preview">
                <div class="wa-bubble"><strong><?= lang('marketing_preview_greeting') ?> <span id="preview-name"><?= lang('marketing_preview_name') ?></span>!</strong>

<?= lang('marketing_preview_offer') ?> 🎁

<?= lang('marketing_preview_procedure') ?> <span id="preview-procedure" class="wa-placeholder"><?= lang('campaign_procedure') ?></span> <?= lang('marketing_preview_discount') ?> <span id="preview-discount" class="wa-placeholder">0</span>% 💰

<?= lang('marketing_preview_valid_until') ?>: <span id="preview-valid-until" class="wa-placeholder"><?= lang('campaign_valid_until') ?></span>

📍 Body Sculpt Clinique
Str. Berzei nr. 16

<?= lang('marketing_preview_cta') ?> ✅</div>
            </div>
        </div>
    </div>

    <div class="row d-none" id="send-progress-section">
        <div class="col-12 col-lg-10 col-xl-8 mb-4">
            <h5 class="fw-light mb-3"><?= lang('sending_progress') ?></h5>

            <div class="progress mb-2" style="height: 24px;">
                <div id="send-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                     role="progressbar" style="width: 0%;"></div>
            </div>

            <p class="text-muted" id="send-progress-text"></p>

            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th><?= lang('customer') ?></th>
                            <th><?= lang('phone_number') ?></th>
                            <th><?= lang('status') ?></th>
                        </tr>
                    </thead>
                    <tbody id="send-results-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/pages/marketing.js') ?>"></script>

<?php end_section('scripts'); ?>
