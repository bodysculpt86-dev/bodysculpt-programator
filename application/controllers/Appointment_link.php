<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.6.0
 * ---------------------------------------------------------------------------- */

/**
 * Public appointment link controller.
 *
 * Allows customers to confirm or cancel an upcoming appointment via a secure token URL.
 *
 * @package Controllers
 */
class Appointment_link extends EA_Controller
{
    /**
     * Appointment_link constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('customers_model');
    }

    /**
     * Display the appointment details with confirm/cancel buttons.
     *
     * @param string $token Confirmation token.
     */
    public function index(string $token = ''): void
    {
        try {
            method('get');

            $appointment = $this->find_appointment_by_token($token);

            if (empty($appointment)) {
                $this->show_error(lang('appointment_not_found'), lang('appointment_does_not_exist_in_db'));

                return;
            }

            $this->load_appointment_relations($appointment);

            html_vars([
                'page_title' => lang('appointment_confirmation'),
                'company_color' => setting('company_color'),
                'appointment' => $appointment,
                'csrf_token' => $this->security->get_csrf_hash(),
                'google_analytics_code' => setting('google_analytics_code'),
                'matomo_analytics_url' => setting('matomo_analytics_url'),
                'matomo_analytics_site_id' => setting('matomo_analytics_site_id'),
                'display_login_button' => setting('display_login_button'),
                'legal_notice_url' => setting('legal_notice_url'),
                'imprint_url' => setting('imprint_url'),
            ]);

            $this->load->view('pages/appointment_link');
        } catch (Throwable $e) {
            log_message('error', 'Appointment Link Exception: ' . $e->getMessage());

            $this->show_error(lang('appointment_not_found'), $e->getMessage());
        }
    }

    /**
     * Confirm the appointment.
     *
     * @param string $token Confirmation token.
     */
    public function confirm(string $token = ''): void
    {
        try {
            method('post');

            $this->apply_rate_limit();

            $appointment = $this->find_appointment_by_token($token);

            if (empty($appointment)) {
                $this->show_result(
                    lang('appointment_not_found'),
                    lang('appointment_does_not_exist_in_db'),
                    'error',
                );

                return;
            }

            $appointment['status'] = APPOINTMENT_STATUS_CONFIRMED_BY_CLIENT;
            $appointment['sms_reminder_sent_at'] = date('Y-m-d H:i:s');

            $this->appointments_model->save($appointment);

            $this->show_result(
                lang('success'),
                lang('appointment_confirmed_by_client_message'),
                'success',
            );
        } catch (Throwable $e) {
            log_message('error', 'Appointment Link Confirm Exception: ' . $e->getMessage());

            $this->show_result(lang('appointment_not_found'), $e->getMessage(), 'error');
        }
    }

    /**
     * Soft-cancel the appointment.
     *
     * @param string $token Confirmation token.
     */
    public function cancel(string $token = ''): void
    {
        try {
            method('post');

            $this->apply_rate_limit();

            $appointment = $this->find_appointment_by_token($token);

            if (empty($appointment)) {
                $this->show_result(
                    lang('appointment_not_found'),
                    lang('appointment_does_not_exist_in_db'),
                    'error',
                );

                return;
            }

            $appointment['status'] = APPOINTMENT_STATUS_CANCELLED_BY_CLIENT;

            $this->appointments_model->save($appointment);

            $this->show_result(
                lang('appointment_cancelled_title'),
                lang('appointment_cancelled_by_client_message'),
                'success',
            );
        } catch (Throwable $e) {
            log_message('error', 'Appointment Link Cancel Exception: ' . $e->getMessage());

            $this->show_result(lang('appointment_not_found'), $e->getMessage(), 'error');
        }
    }

    /**
     * Find an appointment by its confirmation token.
     *
     * @param string $token
     *
     * @return array|null
     */
    private function find_appointment_by_token(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
            return null;
        }

        $occurrences = $this->appointments_model->get(['confirmation_token' => $token]);

        return $occurrences[0] ?? null;
    }

    /**
     * Load service, provider and customer relations for the appointment.
     *
     * @param array $appointment
     */
    private function load_appointment_relations(array &$appointment): void
    {
        $this->appointments_model->load($appointment, ['service', 'provider', 'customer']);
    }

    /**
     * Render a generic error message page.
     *
     * @param string $title
     * @param string $text
     */
    private function show_error(string $title, string $text): void
    {
        html_vars([
            'page_title' => $title,
            'company_color' => setting('company_color'),
            'message_title' => $title,
            'message_text' => $text,
            'message_icon' => base_url('assets/img/error.png'),
            'google_analytics_code' => setting('google_analytics_code'),
            'matomo_analytics_url' => setting('matomo_analytics_url'),
            'matomo_analytics_site_id' => setting('matomo_analytics_site_id'),
            'display_login_button' => setting('display_login_button'),
            'legal_notice_url' => setting('legal_notice_url'),
            'imprint_url' => setting('imprint_url'),
        ]);

        $this->load->view('pages/appointment_link_result');
    }

    /**
     * Render a result page.
     *
     * @param string $title
     * @param string $text
     * @param string $type success|error
     */
    private function show_result(string $title, string $text, string $type): void
    {
        html_vars([
            'page_title' => $title,
            'company_color' => setting('company_color'),
            'message_title' => $title,
            'message_text' => $text,
            'message_icon' => base_url($type === 'success' ? 'assets/img/success.png' : 'assets/img/error.png'),
            'google_analytics_code' => setting('google_analytics_code'),
            'matomo_analytics_url' => setting('matomo_analytics_url'),
            'matomo_analytics_site_id' => setting('matomo_analytics_site_id'),
            'display_login_button' => setting('display_login_button'),
            'legal_notice_url' => setting('legal_notice_url'),
            'imprint_url' => setting('imprint_url'),
        ]);

        $this->load->view('pages/appointment_link_result');
    }

    /**
     * Apply rate limiting for confirm/cancel attempts.
     *
     * @throws RuntimeException If rate limit is exceeded.
     */
    private function apply_rate_limit(): void
    {
        try {
            $this->load->driver('cache', ['adapter' => 'file']);

            if (!isset($this->cache) || !is_object($this->cache)) {
                log_message('debug', 'Cache driver not available, skipping appointment link rate limit check.');
                return;
            }

            $ip = $this->input->ip_address();
            $cache_key = 'appointment_link_attempts_' . str_replace([':', '.'], '_', $ip);

            $attempts = $this->cache->get($cache_key);

            if ($attempts === false) {
                $this->cache->save($cache_key, 1, 600); // 10 minutes
                return;
            }

            $this->cache->save($cache_key, $attempts + 1, 600);

            if ($attempts >= 10) {
                log_message('error', 'Appointment link rate limit exceeded for IP: ' . $ip);
                throw new RuntimeException('Too many attempts. Please try again later.');
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            log_message('error', 'Cache error in appointment link rate limiting: ' . $e->getMessage());
        }
    }
}
