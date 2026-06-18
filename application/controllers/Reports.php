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
}
