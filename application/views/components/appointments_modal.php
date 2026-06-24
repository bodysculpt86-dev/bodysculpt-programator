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
