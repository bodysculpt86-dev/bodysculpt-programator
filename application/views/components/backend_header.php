<?php
/**
 * Local variables.
 *
 * @var string $active_menu
 * @var string $company_logo
 */
?>

<!-- Desktop fixed left sidebar -->
<aside id="header" class="backend-sidebar d-none d-lg-flex flex-column">
    <div class="backend-sidebar-toggle-wrapper d-none d-lg-flex">
        <button type="button" class="backend-sidebar-toggle" data-tippy-content="Comută meniul">
            <i class="fas fa-angle-double-left"></i>
        </button>
    </div>

    <div class="backend-sidebar-brand">
        <img src="<?= base_url(
            'assets/img/logo.png',
        ) ?>" alt="logo" class="backend-sidebar-logo">
        <div class="backend-sidebar-brand-text">
            <h6 class="backend-sidebar-company"><?= e(setting('company_name')) ?></h6>
            <small>Bookings by Revclar</small>
        </div>
    </div>

    <nav class="backend-sidebar-nav nav flex-column flex-grow-1">
        <?php $hidden = can('view', PRIV_APPOINTMENTS) ? '' : 'd-none'; ?>
        <?php $active = $active_menu == PRIV_APPOINTMENTS ? 'active' : ''; ?>
        <a href="<?= site_url('calendar') ?>"
           class="backend-sidebar-link <?= $active . $hidden ?>"
           data-tippy-content="<?= lang('manage_appointment_record_hint') ?>">
            <i class="fas fa-calendar-alt backend-sidebar-icon"></i>
            <span><?= lang('calendar') ?></span>
        </a>

        <?php $hidden = can('view', PRIV_CUSTOMERS) ? '' : 'd-none'; ?>
        <?php $active = $active_menu == PRIV_CUSTOMERS ? 'active' : ''; ?>
        <a href="<?= site_url('customers') ?>"
           class="backend-sidebar-link <?= $active . $hidden ?>"
           data-tippy-content="<?= lang('manage_customers_hint') ?>">
            <i class="fas fa-user-friends backend-sidebar-icon"></i>
            <span><?= lang('customers') ?></span>
        </a>

        <?php if (session('role_slug') === DB_SLUG_ADMIN || session('role_slug') === DB_SLUG_PROVIDER): ?>
            <?php $active = $active_menu === 'reports' ? 'active' : ''; ?>
            <div class="backend-sidebar-dropdown dropend <?= $active ?>">
                <a class="backend-sidebar-link dropdown-toggle" href="#"
                   data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-chart-line backend-sidebar-icon"></i>
                    <span><?= lang('reports') ?></span>
                    <i class="fas fa-chevron-right backend-sidebar-caret ms-auto"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark backend-sidebar-dropdown-menu">
                    <?php if (session('role_slug') === DB_SLUG_ADMIN): ?>
                        <li>
                            <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('reports') ?>">
                                <?= lang('activity_report') ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('reports/by_employee') ?>">
                            <?= lang('employee_report') ?>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('reports/activity') ?>">
                            <?= lang('activity_matrix_report') ?>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (can('view', PRIV_SERVICES)): ?>
            <?php $active = $active_menu == PRIV_SERVICES ? 'active' : ''; ?>
            <div class="backend-sidebar-dropdown dropend <?= $active ?>">
                <a class="backend-sidebar-link dropdown-toggle" href="#"
                   data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-business-time backend-sidebar-icon"></i>
                    <span><?= lang('services') ?></span>
                    <i class="fas fa-chevron-right backend-sidebar-caret ms-auto"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark backend-sidebar-dropdown-menu">
                    <li>
                        <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('services') ?>">
                            <?= lang('services') ?>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('service_categories') ?>">
                            <?= lang('categories') ?>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (can('view', PRIV_USERS)): ?>
            <?php $active = $active_menu == PRIV_USERS ? 'active' : ''; ?>
            <div class="backend-sidebar-dropdown dropend <?= $active ?>">
                <a class="backend-sidebar-link dropdown-toggle" href="#"
                   data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-users backend-sidebar-icon"></i>
                    <span><?= lang('users') ?></span>
                    <i class="fas fa-chevron-right backend-sidebar-caret ms-auto"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark backend-sidebar-dropdown-menu">
                    <li>
                        <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('providers') ?>">
                            <?= lang('providers') ?>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('secretaries') ?>">
                            <?= lang('secretaries') ?>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('admins') ?>">
                            <?= lang('admins') ?>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </nav>

    <?php if (can('view', PRIV_SYSTEM_SETTINGS) || can('view', PRIV_USER_SETTINGS)): ?>
        <?php $active = $active_menu == PRIV_SYSTEM_SETTINGS ? 'active' : ''; ?>
        <div class="backend-sidebar-footer">
            <div class="backend-sidebar-dropdown dropend <?= $active ?>">
                <a class="backend-sidebar-link dropdown-toggle" href="#"
                   data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user backend-sidebar-icon"></i>
                    <span><?= e(vars('user_display_name')) ?></span>
                    <i class="fas fa-chevron-right backend-sidebar-caret ms-auto"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark backend-sidebar-dropdown-menu">
                    <?php if (can('view', PRIV_SYSTEM_SETTINGS)): ?>
                        <li>
                            <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('general_settings') ?>">
                                <i class="fas fa-cogs me-2"></i>
                                <?= lang('settings') ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('account') ?>">
                            <i class="fas fa-user me-2"></i>
                            <?= lang('account') ?>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('about') ?>">
                            <i class="fas fa-info-circle me-2"></i>
                            <?= lang('about') ?>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('booking') ?>" target="_blank">
                            <i class="fas fa-external-link me-2"></i>
                            <?= lang('booking') ?>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item backend-sidebar-sublink" href="<?= site_url('logout') ?>">
                            <i class="fas fa-sign-out me-2"></i>
                            <?= lang('log_out') ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</aside>

<!-- Mobile header -->
<nav class="backend-mobile-header d-lg-none navbar navbar-dark">
    <div class="container-fluid">
        <button class="navbar-toggler backend-mobile-toggle" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#header-menu-offcanvas"
                aria-controls="header-menu-offcanvas">
            <span class="navbar-toggler-icon"></span>
        </button>
        <span class="backend-mobile-brand"><?= e(setting('company_name')) ?></span>
    </div>
</nav>

<!-- Mobile offcanvas sidebar -->
<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="header-menu-offcanvas">
    <div class="offcanvas-header">
        <div class="backend-sidebar-brand">
            <img src="<?= base_url(
                'assets/img/logo.png',
            ) ?>" alt="logo" class="backend-sidebar-logo">
            <div class="backend-sidebar-brand-text">
                <h6 class="backend-sidebar-company"><?= e(setting('company_name')) ?></h6>
                <small>Bookings by Revclar</small>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <nav class="backend-sidebar-nav nav flex-column flex-grow-1">
            <?php $hidden = can('view', PRIV_APPOINTMENTS) ? '' : 'd-none'; ?>
            <?php $active = $active_menu == PRIV_APPOINTMENTS ? 'active' : ''; ?>
            <a href="<?= site_url('calendar') ?>"
               class="backend-sidebar-link <?= $active . $hidden ?>"
               data-tippy-content="<?= lang('manage_appointment_record_hint') ?>">
                <i class="fas fa-calendar-alt backend-sidebar-icon"></i>
                <span><?= lang('calendar') ?></span>
            </a>

            <?php $hidden = can('view', PRIV_CUSTOMERS) ? '' : 'd-none'; ?>
            <?php $active = $active_menu == PRIV_CUSTOMERS ? 'active' : ''; ?>
            <a href="<?= site_url('customers') ?>"
               class="backend-sidebar-link <?= $active . $hidden ?>"
               data-tippy-content="<?= lang('manage_customers_hint') ?>">
                <i class="fas fa-user-friends backend-sidebar-icon"></i>
                <span><?= lang('customers') ?></span>
            </a>

            <?php if (session('role_slug') === DB_SLUG_ADMIN || session('role_slug') === DB_SLUG_PROVIDER): ?>
                <?php $active = $active_menu === 'reports' ? 'active' : ''; ?>
                <div class="backend-sidebar-dropdown <?= $active ?>">
                    <a class="backend-sidebar-link dropdown-toggle" href="#"
                       data-bs-toggle="collapse" data-bs-target="#sidebar-reports-submenu-mobile"
                       aria-expanded="<?= $active ? 'true' : 'false' ?>">
                        <i class="fas fa-chart-line backend-sidebar-icon"></i>
                        <span><?= lang('reports') ?></span>
                        <i class="fas fa-chevron-down backend-sidebar-caret ms-auto"></i>
                    </a>
                    <div class="collapse backend-sidebar-submenu <?= $active ? 'show' : '' ?>" id="sidebar-reports-submenu-mobile">
                        <?php if (session('role_slug') === DB_SLUG_ADMIN): ?>
                            <a class="backend-sidebar-sublink" href="<?= site_url('reports') ?>">
                                <?= lang('activity_report') ?>
                            </a>
                        <?php endif; ?>
                        <a class="backend-sidebar-sublink" href="<?= site_url('reports/by_employee') ?>">
                            <?= lang('employee_report') ?>
                        </a>
                        <a class="backend-sidebar-sublink" href="<?= site_url('reports/activity') ?>">
                            <?= lang('activity_matrix_report') ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (can('view', PRIV_SERVICES)): ?>
                <?php $active = $active_menu == PRIV_SERVICES ? 'active' : ''; ?>
                <div class="backend-sidebar-dropdown <?= $active ?>">
                    <a class="backend-sidebar-link dropdown-toggle" href="#"
                       data-bs-toggle="collapse" data-bs-target="#sidebar-services-submenu-mobile"
                       aria-expanded="<?= $active ? 'true' : 'false' ?>">
                        <i class="fas fa-business-time backend-sidebar-icon"></i>
                        <span><?= lang('services') ?></span>
                        <i class="fas fa-chevron-down backend-sidebar-caret ms-auto"></i>
                    </a>
                    <div class="collapse backend-sidebar-submenu <?= $active ? 'show' : '' ?>" id="sidebar-services-submenu-mobile">
                        <a class="backend-sidebar-sublink" href="<?= site_url('services') ?>">
                            <?= lang('services') ?>
                        </a>
                        <a class="backend-sidebar-sublink" href="<?= site_url('service_categories') ?>">
                            <?= lang('categories') ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (can('view', PRIV_USERS)): ?>
                <?php $active = $active_menu == PRIV_USERS ? 'active' : ''; ?>
                <div class="backend-sidebar-dropdown <?= $active ?>">
                    <a class="backend-sidebar-link dropdown-toggle" href="#"
                       data-bs-toggle="collapse" data-bs-target="#sidebar-users-submenu-mobile"
                       aria-expanded="<?= $active ? 'true' : 'false' ?>">
                        <i class="fas fa-users backend-sidebar-icon"></i>
                        <span><?= lang('users') ?></span>
                        <i class="fas fa-chevron-down backend-sidebar-caret ms-auto"></i>
                    </a>
                    <div class="collapse backend-sidebar-submenu <?= $active ? 'show' : '' ?>" id="sidebar-users-submenu-mobile">
                        <a class="backend-sidebar-sublink" href="<?= site_url('providers') ?>">
                            <?= lang('providers') ?>
                        </a>
                        <a class="backend-sidebar-sublink" href="<?= site_url('secretaries') ?>">
                            <?= lang('secretaries') ?>
                        </a>
                        <a class="backend-sidebar-sublink" href="<?= site_url('admins') ?>">
                            <?= lang('admins') ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>

        <?php if (can('view', PRIV_SYSTEM_SETTINGS) || can('view', PRIV_USER_SETTINGS)): ?>
            <?php $active = $active_menu == PRIV_SYSTEM_SETTINGS ? 'active' : ''; ?>
            <div class="backend-sidebar-footer">
                <div class="backend-sidebar-dropdown <?= $active ?>">
                    <a class="backend-sidebar-link dropdown-toggle" href="#"
                       data-bs-toggle="collapse" data-bs-target="#sidebar-account-submenu-mobile"
                       aria-expanded="<?= $active ? 'true' : 'false' ?>">
                        <i class="fas fa-user backend-sidebar-icon"></i>
                        <span><?= e(vars('user_display_name')) ?></span>
                        <i class="fas fa-chevron-down backend-sidebar-caret ms-auto"></i>
                    </a>
                    <div class="collapse backend-sidebar-submenu <?= $active ? 'show' : '' ?>" id="sidebar-account-submenu-mobile">
                        <?php if (can('view', PRIV_SYSTEM_SETTINGS)): ?>
                            <a class="backend-sidebar-sublink" href="<?= site_url('general_settings') ?>">
                                <i class="fas fa-cogs me-2"></i>
                                <?= lang('settings') ?>
                            </a>
                        <?php endif; ?>
                        <a class="backend-sidebar-sublink" href="<?= site_url('account') ?>">
                            <i class="fas fa-user me-2"></i>
                            <?= lang('account') ?>
                        </a>
                        <a class="backend-sidebar-sublink" href="<?= site_url('about') ?>">
                            <i class="fas fa-info-circle me-2"></i>
                            <?= lang('about') ?>
                        </a>
                        <a class="backend-sidebar-sublink" href="<?= site_url('booking') ?>" target="_blank">
                            <i class="fas fa-external-link me-2"></i>
                            <?= lang('booking') ?>
                        </a>
                        <a class="backend-sidebar-sublink" href="<?= site_url('logout') ?>">
                            <i class="fas fa-sign-out me-2"></i>
                            <?= lang('log_out') ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="notification" style="display: none;"></div>

<div id="loading" class="position-fixed top-0 start-0 w-100 h-100" style="display: none; z-index: 999999; background: rgba(255, 255, 255, 0.75);">
    <div class="any-element animation is-loading d-block mx-auto">
        &nbsp;
    </div>
</div>
