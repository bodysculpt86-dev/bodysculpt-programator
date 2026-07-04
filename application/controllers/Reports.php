<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Reports controller.
 *
 * Handles revenue reporting pages and AJAX endpoints.
 *
 * @package Controllers
 */
class Reports extends EA_Controller
{
    /**
     * Reports constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('providers_model');

        $this->load->library('accounts');
    }

    /**
     * Render the revenue reports page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('reports')]);

        $user_id = session('user_id');

        if (session('role_slug') !== DB_SLUG_ADMIN) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        script_vars([
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
            'first_weekday' => setting('first_weekday'),
            'currency' => setting('currency'),
        ]);

        html_vars([
            'page_title' => lang('revenue_report'),
            'active_menu' => 'reports',
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
        ]);

        $this->load->view('pages/reports');
    }

    /**
     * Return daily and monthly revenue for the requested date range.
     */
    public function get_revenue(): void
    {
        try {
            method('post');

            if (session('role_slug') !== DB_SLUG_ADMIN) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('start_date', 'date');
            check('end_date', 'date');

            $start_date = request('start_date');
            $end_date = request('end_date');

            json_response([
                'daily' => $this->appointments_model->get_daily_revenue($start_date, $end_date),
                'monthly' => $this->appointments_model->get_monthly_revenue($start_date, $end_date),
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Render the per-employee revenue report page.
     */
    public function by_employee(): void
    {
        method('get');

        session(['dest_url' => site_url('reports/by_employee')]);

        $user_id = session('user_id');
        $role_slug = session('role_slug');

        // Only admins and providers may access this report.
        if ($role_slug !== DB_SLUG_ADMIN && $role_slug !== DB_SLUG_PROVIDER) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $is_admin = $role_slug === DB_SLUG_ADMIN;

        // The provider list is only needed for the admin dropdown.
        $providers = $is_admin ? $this->providers_model->get_available_providers() : [];

        script_vars([
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
            'first_weekday' => setting('first_weekday'),
            'currency' => setting('currency'),
            'is_admin' => $is_admin,
        ]);

        html_vars([
            'page_title' => lang('employee_report'),
            'active_menu' => 'reports',
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'providers' => $providers,
            'is_admin' => $is_admin,
        ]);

        $this->load->view('pages/reports_by_employee');
    }

    /**
     * Return daily and monthly revenue for the requested employee and date range.
     */
    public function get_employee_revenue(): void
    {
        try {
            method('post');

            $role_slug = session('role_slug');
            $user_id = session('user_id');

            // Only admins and providers may access this report.
            if ($role_slug !== DB_SLUG_ADMIN && $role_slug !== DB_SLUG_PROVIDER) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('start_date', 'date');
            check('end_date', 'date');

            $start_date = request('start_date');
            $end_date = request('end_date');

            if ($role_slug === DB_SLUG_ADMIN) {
                // Admins may request any provider, but we verify it is a real provider.
                $requested = (int) request('employee_id');

                if ($requested <= 0) {
                    throw new InvalidArgumentException('Invalid employee.');
                }

                $valid_ids = array_column(
                    $this->providers_model->get_available_providers(),
                    'id',
                );

                if (!in_array($requested, array_map('intval', $valid_ids), true)) {
                    throw new InvalidArgumentException('Invalid employee.');
                }

                $provider_id = $requested;
            } else {
                // Providers are strictly locked to their own user ID, ignoring any request value.
                $provider_id = (int) $user_id;
            }

            json_response([
                'daily' => $this->appointments_model->get_daily_revenue($start_date, $end_date, $provider_id),
                'monthly' => $this->appointments_model->get_monthly_revenue($start_date, $end_date, $provider_id),
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Render the activity matrix report page (admin only).
     */
    public function activity(): void
    {
        method('get');

        session(['dest_url' => site_url('reports/activity')]);

        $user_id = session('user_id');

        if (session('role_slug') !== DB_SLUG_ADMIN) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $closing_statuses = json_decode(setting('appointment_closing_statuses'), true) ?? [];
        $payment_options = array_values(array_diff($closing_statuses, ['Nu s-a prezentat']));

        script_vars([
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
            'first_weekday' => setting('first_weekday'),
            'currency' => setting('currency'),
        ]);

        html_vars([
            'page_title' => lang('activity_matrix_report'),
            'active_menu' => 'reports',
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'providers' => $this->providers_model->get_available_providers(),
            'payment_options' => $payment_options,
        ]);

        $this->load->view('pages/reports_activity');
    }

    /**
     * Return the activity matrix data for the requested date range (admin only).
     */
    public function get_activity(): void
    {
        try {
            method('post');

            if (session('role_slug') !== DB_SLUG_ADMIN) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('start_date', 'date');
            check('end_date', 'date');

            $start_date = request('start_date');
            $end_date = request('end_date');

            $payment_statuses = request('payment_statuses');

            if (!is_array($payment_statuses)) {
                $payment_statuses = null;
            }

            $group_by = request('group_by') ?: 'month';

            json_response($this->appointments_model->get_activity_matrix($start_date, $end_date, $payment_statuses, $group_by));
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
