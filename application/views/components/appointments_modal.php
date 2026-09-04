<?php
/**
 * Local variables.
 *
 * @var array $available_services
 * @var array $appointment_status_options
 * @var array $require_first_name
 * @var array $require_last_name
 * @var array $require_email
 * @var array $require_phone_number
 * @var array $require_address
 * @var array $require_city
 * @var array $require_zip_code
 * @var array $require_notes
 */
?>
<div id="appointments-modal" class="modal fade">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-lg-down modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><?= lang('edit_appointment_title') ?></h3>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="modal-message alert d-none"></div>

                <div id="deposit-status-area" class="alert d-none mb-3 py-2" role="status">
                    <i id="deposit-status-icon" class="fas me-2"></i>
                    <strong id="deposit-status-label"></strong>
                    <span id="deposit-status-details" class="ms-2"></span>
                </div>

                <form>
                    <input id="appointment-id" type="hidden">

                    <fieldset>
                        <h5 class="mb-3 fw-light">
                            <?= lang('customer_details_title') ?>
                            <button id="new-customer" class="btn btn-outline-secondary btn-sm" type="button"
                                    data-tippy-content="<?= lang('clear_fields_add_existing_customer_hint') ?>">
                                <i class="fas fa-plus-square me-2"></i>
                                <?= lang('new') ?>
                            </button>
                            <button id="select-customer" class="btn btn-outline-secondary btn-sm" type="button"
                                    data-tippy-content="<?= lang('pick_existing_customer_hint') ?>">
                                <i class="fas fa-hand-pointer me-2"></i>
                                <span>
                                    <?= lang('select') ?>
                                </span>
                            </button>
                            <button id="select-meta-lead" class="btn btn-outline-secondary btn-sm" type="button"
                                    data-tippy-content="<?= lang('pick_meta_lead_hint') ?>">
                                <i class="fas fa-user-plus me-2"></i>
                                <span>
                                    <?= lang('import_meta_lead') ?>
                                </span>
                            </button>

                            <input id="filter-existing-customers"
                                   placeholder="<?= lang('type_to_filter_customers') ?>"
                                   style="display: none;" class="input-sm form-control">
                        </h5>

                        <div id="existing-customers-list" style="display: none;"></div>

                        <div id="meta-leads-list" style="display: none;"></div>

                        <input id="customer-id" type="hidden">

                        <input id="meta-lead-id" type="hidden">

                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="mb-3">
                                    <label for="first-name" class="form-label">
                                        <?= lang('first_name') ?>
                                        <?php if ($require_first_name): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="first-name"
                                           class="<?= $require_first_name ? 'required' : '' ?> form-control"
                                           maxlength="100"/>
                                </div>

                                <div class="mb-3">
                                    <label for="last-name" class="form-label">
                                        <?= lang('last_name') ?>
                                        <?php if ($require_last_name): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="last-name"
                                           class="<?= $require_last_name ? 'required' : '' ?> form-control"
                                           maxlength="120"/>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <?= lang('email') ?>
                                        <?php if ($require_email): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="email"
                                           class="<?= $require_email ? 'required' : '' ?> form-control"
                                           maxlength="120"/>
                                </div>

                                <div class="mb-3">
                                    <label for="phone-number" class="form-label">
                                        <?= lang('phone_number') ?>
                                        <?php if ($require_phone_number): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <div class="input-group">
                                        <select id="phone-prefix" class="form-select phone-prefix-select"
                                                aria-label="<?= lang('phone_number') ?> prefix"></select>
                                        <input type="tel" id="phone-number" maxlength="60"
                                               class="<?= $require_phone_number ? 'required' : '' ?> form-control"
                                               placeholder="712345678"/>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="language">
                                        <?= lang('language') ?>
                                        <span class="text-danger" hidden>*</span>
                                    </label>
                                    <select id="language" class="form-select required">
                                        <?php foreach (vars('available_languages') as $available_language): ?>
                                            <option value="<?= $available_language ?>">
                                                <?= ucfirst($available_language) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php component('custom_fields'); ?>

                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="mb-3">
                                    <label for="address" class="form-label">
                                        <?= lang('address') ?>
                                        <?php if ($require_address): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="address"
                                           class="<?= $require_address ? 'required' : '' ?> form-control"
                                           maxlength="120"/>
                                </div>

                                <div class="mb-3">
                                    <label for="city" class="form-label">
                                        <?= lang('city') ?>
                                        <?php if ($require_city): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="city"
                                           class="<?= $require_city ? 'required' : '' ?> form-control"
                                           maxlength="120"/>
                                </div>

                                <div class="mb-3">
                                    <label for="state" class="form-label">
                                        <?= lang('county') ?>
                                    </label>
                                    <input type="text" id="state" class="form-control" maxlength="120"/>
                                </div>

                                <div class="mb-3">
                                    <label for="zip-code" class="form-label">
                                        <?= lang('zip_code') ?>
                                        <?php if ($require_zip_code): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="zip-code"
                                           class="<?= $require_zip_code ? 'required' : '' ?> form-control"
                                           maxlength="120"/>
                                </div>

                                <div class="mb-3">
                                    <label for="customer-notes" class="form-label">
                                        <?= lang('notes') ?>
                                    </label>
                                    <textarea id="customer-notes" rows="3" class="form-control"></textarea>
                                </div>

                            </div>
                        </div>
                    </fieldset>

                    <br>

                    <fieldset>
                        <h5 class="mb-3 fw-light"><?= lang('appointment_details_title') ?></h5>

                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="mb-3">
                                    <label for="select-appointment-type" class="form-label">
                                        <?= lang('type') ?>
                                    </label>
                                    <select id="select-appointment-type" class="form-select">
                                        <option value="service"><?= lang('service') ?></option>
                                        <option value="package"><?= lang('customer_package') ?></option>
                                    </select>
                                </div>

                                <div class="mb-3" id="customer-package-wrapper" style="display: none;">
                                    <label for="select-customer-package" class="form-label">
                                        <?= lang('customer_package') ?>
                                    </label>
                                    <select id="select-customer-package" class="form-select">
                                        <option value=""><?= lang('please_select') ?></option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="select-service-category" class="form-label">
                                        <?= lang('category') ?>
                                        <span class="text-danger">*</span>
                                    </label>

                                    <?php
                                    $grouped_services = [];
                                    $uncategorized_services = [];

                                    foreach ($available_services as $service) {
                                        if (!empty($service['service_category_id'])) {
                                            $category_name = $service['service_category_name'] ?: lang('service_category');
                                            $grouped_services[$category_name][] = $service;
                                        } else {
                                            $uncategorized_services[] = $service;
                                        }
                                    }

                                    ksort($grouped_services);
                                    ?>

                                    <select id="select-service-category" class="required form-select mb-3">
                                        <option value="">
                                            <?= lang('please_select') ?>
                                        </option>
                                        <?php foreach ($grouped_services as $category_name => $services): ?>
                                            <option value="<?= e($category_name) ?>">
                                                <?= e($category_name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if (!empty($uncategorized_services)): ?>
                                            <option value="uncategorized">
                                                <?= lang('service') ?>
                                            </option>
                                        <?php endif; ?>
                                    </select>

                                    <label for="select-service" class="form-label">
                                        <?= lang('service') ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select id="select-service" class="required form-select">
                                        <option value="">
                                            <?= lang('please_select') ?>
                                        </option>
                                    </select>
                                </div>

                                <div id="service-price-wrapper">
                                    <div class="mb-3">
                                        <label for="appointment-price" class="form-label">
                                            <?= lang('price') ?>
                                            <span class="text-muted">(Lei)</span>
                                        </label>
                                        <input id="appointment-price" class="form-control appointment-service-price" type="number" step="0.01" min="0" placeholder="0.00">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">
                                            <?= lang('additional_services') ?>
                                        </label>
                                        <div id="additional-services"></div>
                                        <button id="add-additional-service" class="btn btn-outline-secondary btn-sm" type="button">
                                            <i class="fas fa-plus me-2"></i>
                                            <?= lang('add_service') ?>
                                        </button>
                                        <div class="mt-2 text-end fw-bold">
                                            <?= lang('total') ?>:
                                            <span id="appointment-services-total">0.00</span> Lei
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="select-provider" class="form-label">
                                        <?= lang('provider') ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select id="select-provider" class="required form-select"></select>
                                </div>

                                <div class="mb-3">
                                    <?php component('color_selection', ['attributes' => 'id="appointment-color"']); ?>
                                </div>

                                <div class="mb-3">
                                    <label for="appointment-location" class="form-label">
                                        <?= lang('location') ?>
                                    </label>
                                    <input id="appointment-location" class="form-control">
                                </div>

                                <div class="mb-3">
                                    <label for="appointment-meeting-link" class="form-label">
                                        <?= lang('meeting_link') ?>
                                    </label>
                                    <input id="appointment-meeting-link" class="form-control" placeholder="https://">
                                </div>

                                <div class="mb-3">
                                    <label for="appointment-status" class="form-label">
                                        <?= lang('status') ?>
                                    </label>
                                    <select id="appointment-status" class="form-select">
                                        <?php foreach ($appointment_status_options as $appointment_status_option): ?>
                                            <option value="<?= e($appointment_status_option) ?>">
                                                <?= e($appointment_status_option) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="appointment-close-status" class="form-label">
                                        <?= lang('close_appointment') ?>
                                    </label>
                                    <select id="appointment-close-status" class="form-select">
                                        <option value="">—</option>
                                        <?php foreach ($appointment_closing_statuses as $appointment_closing_status): ?>
                                            <?php $status_class = 'status-' . preg_replace('/[^a-z0-9]+/', '-', strtolower(str_replace(['ă', 'â', 'î', 'ș', 'ț'], ['a', 'a', 'i', 's', 't'], $appointment_closing_status))); ?>
                                            <option value="<?= e($appointment_closing_status) ?>"
                                                    data-status-class="<?= e($status_class) ?>">
                                                <?= e($appointment_closing_status) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <div class="mb-3">
                                    <label for="start-datetime"
                                           class="form-label"><?= lang('start_date_time') ?></label>
                                    <input id="start-datetime" class="required form-control">
                                </div>

                                <div class="mb-3">
                                    <label for="end-datetime" class="form-label"><?= lang('end_date_time') ?></label>
                                    <input id="end-datetime" class="required form-control">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        <?= lang('timezone') ?>
                                    </label>

                                    <div
                                        class="border rounded d-flex justify-content-between align-items-center bg-light timezone-info">
                                        <div class="border-end w-50 p-1 text-center">
                                            <small>
                                                <?= lang('provider') ?>:
                                                <span class="provider-timezone">
                                                    -
                                                </span>
                                            </small>
                                        </div>
                                        <div class="w-50 p-1 text-center">
                                            <small>
                                                <?= lang('current_user') ?>:
                                                <span>
                                                    <?= $timezones[setting('default_timezone')] ?>
                                                </span>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="appointment-notes" class="form-label">
                                        <?= lang('notes') ?>
                                        <?php if ($require_notes): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <textarea id="appointment-notes" class="<?= $require_notes
                                        ? 'required'
                                        : '' ?> form-control" rows="3"></textarea>
                                </div>

                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>

            <div class="modal-footer">

                <button id="send-payment-link" class="btn btn-success me-auto" type="button" style="display: none;">
                    <i class="fas fa-credit-card me-2"></i>
                    <span id="payment-link-label"><?= lang('send_payment_link') ?></span>
                </button>
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <?= lang('cancel') ?>
                </button>
                <button id="save-appointment" class="btn btn-primary">
                    <i class="fas fa-check-square me-2"></i>
                    <?= lang('save') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/customer_packages_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/http/payments_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/http/meta_leads_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/components/appointments_modal.js') ?>"></script>

<?php end_section('scripts'); ?>
