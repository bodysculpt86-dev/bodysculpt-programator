<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.4.0
 * ---------------------------------------------------------------------------- */

/**
 * Notifications library.
 *
 * Handles the notifications related functionality.
 *
 * @package Libraries
 */
class Notifications
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Tracks the same-day groups whose grouped confirmation was already sent during the
     * current request, so a batch save never sends the combined message more than once.
     *
     * Keys are "customer_id|date"; the value is irrelevant. Reset on every request
     * (nothing is persisted to the database).
     *
     * @var array
     */
    private static array $sent_grouped_confirmations = [];

    /**
     * Notifications constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('admins_model');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('providers_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('settings_model');

        $this->CI->load->library('email_messages');
        $this->CI->load->library('ics_file');
        $this->CI->load->library('timezones');
        $this->CI->load->library('sms_smso');
        $this->CI->load->library('whatsapp_flaxxa');
    }

    /**
     * Send the required notifications, related to an appointment creation/modification.
     *
     * @param array $appointment Appointment data.
     * @param array $service Service data.
     * @param array $provider Provider data.
     * @param array $customer Customer data.
     * @param array $settings Required settings.
     * @param bool|false $manage_mode Manage mode.
     */
    public function notify_appointment_saved(
        array $appointment,
        array $service,
        array $provider,
        array $customer,
        array $settings,
        bool $manage_mode = false,
    ): void {
        try {
            $current_language = config('language');

            $customer_link = site_url('booking/reschedule/' . $appointment['hash']);

            $provider_link = site_url('calendar/reschedule/' . $appointment['hash']);

            $ics_stream = $this->CI->ics_file->get_stream($appointment, $service, $provider, $customer);

            // Notify customer.
            $send_customer =
                !empty($customer['email']) && filter_var(setting('customer_notifications'), FILTER_VALIDATE_BOOLEAN);

            if ($send_customer === true) {
                config(['language' => $customer['language']]);
                $this->CI->lang->load('translations');
                $subject = $manage_mode ? lang('appointment_details_changed') : lang('appointment_booked');
                $message = $manage_mode ? '' : lang('thank_you_for_appointment');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $subject,
                        $message,
                        $customer_link,
                        $customer['email'],
                        $ics_stream,
                        $customer['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-saved to customer', $appointment['id'] ?? null);
                }
            }

            // Send confirmation SMS + WhatsApp for new appointments only.
            if ($manage_mode === false) {
                [$confirmation_appointment, $confirmation_service, $grouped_count] = $this->build_grouped_confirmation(
                    $appointment,
                    $service,
                );

                // Deduplicate grouped confirmations within a single request: when several new
                // appointments are batch-saved into the same same-day group, only the first
                // one sends the combined confirmation.
                $send_confirmation = true;

                if ($grouped_count > 1) {
                    $grouped_key = self::grouped_confirmation_key($appointment, $confirmation_appointment);

                    if (isset(self::$sent_grouped_confirmations[$grouped_key])) {
                        $send_confirmation = false;
                    } else {
                        self::$sent_grouped_confirmations[$grouped_key] = true;
                    }
                }

                if ($send_confirmation) {
                    try {
                        $this->CI->sms_smso->send_confirmation(
                            $confirmation_appointment,
                            $customer,
                            $confirmation_service,
                            $provider,
                        );
                    } catch (Throwable $e) {
                        $this->log_exception($e, 'appointment-saved sms to customer', $appointment['id'] ?? null);
                    }

                    try {
                        $this->CI->whatsapp_flaxxa->send_confirmation(
                            $confirmation_appointment,
                            $customer,
                            $confirmation_service,
                            $provider,
                        );
                    } catch (Throwable $e) {
                        $this->log_exception($e, 'appointment-saved whatsapp to customer', $appointment['id'] ?? null);
                    }
                }

                if ($grouped_count > 1 && $send_confirmation) {
                    log_message(
                        'debug',
                        '[wa-flaxxa] Grouped confirmation for customer ' .
                            ($customer['id'] ?? 'N/A') .
                            ' — ' .
                            $grouped_count .
                            ' appointments',
                    );
                }
            }

            // Notify provider.
            $send_provider = filter_var(
                $this->CI->providers_model->get_setting($provider['id'], 'notifications'),
                FILTER_VALIDATE_BOOLEAN,
            );

            if ($send_provider === true) {
                config(['language' => $provider['language']]);
                $this->CI->lang->load('translations');
                $subject = $manage_mode ? lang('appointment_details_changed') : lang('appointment_added_to_your_plan');
                $message = $manage_mode ? '' : lang('appointment_link_description');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $subject,
                        $message,
                        $provider_link,
                        $provider['email'],
                        $ics_stream,
                        $provider['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-saved to provider', $appointment['id'] ?? null);
                }
            }

            // Notify admins.
            $admins = $this->CI->admins_model->get();

            foreach ($admins as $admin) {
                if ($admin['settings']['notifications'] === '0') {
                    continue;
                }

                config(['language' => $admin['language']]);
                $this->CI->lang->load('translations');
                $subject = $manage_mode ? lang('appointment_details_changed') : lang('appointment_added_to_your_plan');
                $message = $manage_mode ? '' : lang('appointment_link_description');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $subject,
                        $message,
                        $provider_link,
                        $admin['email'],
                        $ics_stream,
                        $admin['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-saved to admin', $appointment['id'] ?? null);
                }
            }

            // Notify secretaries.
            $secretaries = $this->CI->secretaries_model->get();

            foreach ($secretaries as $secretary) {
                if ($secretary['settings']['notifications'] === '0') {
                    continue;
                }

                if (!in_array($provider['id'], $secretary['providers'])) {
                    continue;
                }

                config(['language' => $secretary['language']]);
                $this->CI->lang->load('translations');
                $subject = $manage_mode ? lang('appointment_details_changed') : lang('appointment_added_to_your_plan');
                $message = $manage_mode ? '' : lang('appointment_link_description');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $subject,
                        $message,
                        $provider_link,
                        $secretary['email'],
                        $ics_stream,
                        $secretary['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-saved to secretary', $appointment['id'] ?? null);
                }
            }
        } catch (Throwable $e) {
            $this->log_exception($e, 'appointment-saved (general exception)', $appointment['id'] ?? null);
        } finally {
            config(['language' => $current_language ?? 'english']);
            $this->CI->lang->load('translations');
        }
    }

    /**
     * Send the required notifications, related to an appointment removal.
     *
     * @param array $appointment Appointment data.
     * @param array $service Service data.
     * @param array $provider Provider data.
     * @param array $customer Customer data.
     * @param array $settings Required settings.
     */
    public function notify_appointment_deleted(
        array $appointment,
        array $service,
        array $provider,
        array $customer,
        array $settings,
        string $cancellation_reason = '',
    ): void {
        try {
            $current_language = config('language');

            // Notify provider.
            $send_provider = filter_var(
                $this->CI->providers_model->get_setting($provider['id'], 'notifications'),
                FILTER_VALIDATE_BOOLEAN,
            );

            if ($send_provider === true) {
                config(['language' => $provider['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $provider['email'],
                        $cancellation_reason,
                        $provider['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-deleted to provider', $appointment['id'] ?? null);
                }
            }

            // Notify customer.
            $send_customer =
                !empty($customer['email']) && filter_var(setting('customer_notifications'), FILTER_VALIDATE_BOOLEAN);

            if ($send_customer === true) {
                config(['language' => $customer['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $customer['email'],
                        $cancellation_reason,
                        $customer['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-deleted to customer', $appointment['id'] ?? null);
                }
            }

            // Notify admins.
            $admins = $this->CI->admins_model->get();

            foreach ($admins as $admin) {
                if ($admin['settings']['notifications'] === '0') {
                    continue;
                }

                config(['language' => $admin['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $admin['email'],
                        $cancellation_reason,
                        $admin['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-deleted to admin', $appointment['id'] ?? null);
                }
            }

            // Notify secretaries.
            $secretaries = $this->CI->secretaries_model->get();

            foreach ($secretaries as $secretary) {
                if ($secretary['settings']['notifications'] === '0') {
                    continue;
                }

                if (!in_array($provider['id'], $secretary['providers'])) {
                    continue;
                }

                config(['language' => $secretary['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $secretary['email'],
                        $cancellation_reason,
                        $secretary['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-deleted to secretary', $appointment['id'] ?? null);
                }
            }
        } catch (Throwable $e) {
            log_message(
                'error',
                'Notifications - Could not email cancellation details of appointment (' .
                    ($appointment['id'] ?? '-') .
                    ') : ' .
                    $e->getMessage(),
            );
            log_message('error', $e->getTraceAsString());
        } finally {
            config(['language' => $current_language ?? 'english']);
            $this->CI->lang->load('translations');
        }
    }

    /**
     * Build the dedup key for a grouped confirmation ("customer_id|date").
     *
     * @param array $appointment The just-saved appointment.
     * @param array $confirmation_appointment The (possibly grouped) confirmation payload.
     *
     * @return string
     */
    private static function grouped_confirmation_key(array $appointment, array $confirmation_appointment): string
    {
        $customer_id = (int) ($appointment['id_users_customer'] ?? 0);
        $date = substr((string) ($confirmation_appointment['start_datetime'] ?? ''), 0, 10);

        return $customer_id . '|' . $date;
    }

    /**
     * Build the appointment + service payload used for the confirmation message.
     *
     * When the saved appointment is part of a larger same-day group, this collapses the
     * whole group into a single payload: the earliest start time and the concatenated
     * service names. Otherwise the original appointment/service are returned untouched.
     *
     * @param array $appointment The just-saved appointment.
     * @param array $service The just-saved appointment's service.
     *
     * @return array [appointment, service, group_count]
     */
    private function build_grouped_confirmation(array $appointment, array $service): array
    {
        $group = $this->CI->appointments_model->get_same_day_group((int) $appointment['id']);

        if (count($group) <= 1) {
            return [$appointment, $service, 1];
        }

        $service_ids = array_column($group, 'id_services');

        $service_rows = $this->CI->db
            ->select('id, name')
            ->where_in('id', $service_ids)
            ->get('services')
            ->result_array();

        $name_by_id = [];

        foreach ($service_rows as $row) {
            $name_by_id[(int) $row['id']] = $row['name'];
        }

        $names = [];

        foreach ($group as $grouped_appointment) {
            $names[] = $name_by_id[(int) $grouped_appointment['id_services']] ?? '';
        }

        $grouped_appointment = $appointment;
        $grouped_appointment['start_datetime'] = $group[0]['start_datetime'];

        $grouped_service = $service;
        $grouped_service['name'] = implode(' + ', array_filter($names));

        return [$grouped_appointment, $grouped_service, count($group)];
    }

    private function log_exception(Throwable $e, string $message, ?int $appointment_id): void
    {
        log_message(
            'error',
            'Notifications - Could not email ' . $message . ' (' . ($appointment_id ?? '-') . ') : ' . $e->getMessage(),
        );
        log_message('error', $e->getTraceAsString());
    }
}
