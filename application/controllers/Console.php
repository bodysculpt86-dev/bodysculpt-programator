<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.3.2
 * ---------------------------------------------------------------------------- */

use Jsvrcek\ICS\Exception\CalendarEventException;

require_once __DIR__ . '/Google.php';
require_once __DIR__ . '/Caldav.php';

/**
 * Console controller.
 *
 * Handles all the Console related operations.
 */
class Console extends EA_Controller
{
    /**
     * Console constructor.
     */
    public function __construct()
    {
        if (!is_cli()) {
            exit('No direct script access allowed');
        }

        parent::__construct();

        $this->load->dbutil();

        $this->load->library('instance');
        $this->load->library('cleanup');
        $this->load->library('sms_smso');
        $this->load->library('whatsapp_flaxxa');

        $this->load->model('admins_model');
        $this->load->model('appointments_model');
        $this->load->model('customers_model');
        $this->load->model('payment_links_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
    }

    /**
     * Perform a console installation.
     *
     * Use this method to install Easy!Appointments directly from the terminal.
     *
     * Usage:
     *
     * php index.php console install
     *
     * @throws Exception
     */
    public function install(): void
    {
        $this->instance->migrate('fresh');

        $password = $this->instance->seed();

        response(
            PHP_EOL . '⇾ Installation completed, login with "administrator" / "' . $password . '".' . PHP_EOL . PHP_EOL,
        );
    }

    /**
     * Bootstrap (or update) the super-admin account from environment variables.
     *
     * Uses SUPERADMIN_EMAIL, SUPERADMIN_USERNAME (defaults to email) and SUPERADMIN_PASSWORD.
     *
     * Usage:
     *
     * php index.php console bootstrap
     */
    public function bootstrap(): void
    {
        $message = $this->instance->bootstrap();

        response(PHP_EOL . '⇾ ' . $message . PHP_EOL . PHP_EOL);
    }

    /**
     * Migrate the database to the latest state.
     *
     * Use this method to upgrade an Easy!Appointments instance to the latest database state.
     *
     * Notice:
     *
     * Do not use this method to install the app as it will not seed the database with the initial entries (admin,
     * provider, service, settings etc.).
     *
     * Usage:
     *
     * php index.php console migrate
     *
     * php index.php console migrate fresh
     *
     * @param string $type
     */
    public function migrate(string $type = ''): void
    {
        $this->instance->migrate($type);
    }

    /**
     * Seed the database with test data.
     *
     * Use this method to add test data to your database
     *
     * Usage:
     *
     * php index.php console seed
     * @throws Exception
     */
    public function seed(): void
    {
        $this->instance->seed();
    }

    /**
     * Create a database backup file.
     *
     * Use this method to back up your Easy!Appointments data.
     *
     * Usage:
     *
     * php index.php console backup
     *
     * php index.php console backup /path/to/backup/folder
     *
     * @throws Exception
     */
    public function backup(): void
    {
        $this->instance->backup($GLOBALS['argv'][3] ?? null);
    }

    /**
     * Trigger the synchronization of all provider calendars with Google Calendar.
     *
     * Use this method in a cronjob to automatically sync events between Easy!Appointments and Google Calendar.
     *
     * Notice:
     *
     * Google syncing must first be enabled for each individual provider from inside the backend calendar page.
     *
     * Usage:
     *
     * php index.php console sync
     *
     * @throws CalendarEventException
     * @throws Exception
     * @throws Throwable
     */
    public function sync(): void
    {
        $providers = $this->providers_model->get();

        foreach ($providers as $provider) {
            if (filter_var($provider['settings']['google_sync'], FILTER_VALIDATE_BOOLEAN)) {
                Google::sync((string) $provider['id']);
            }

            if (filter_var($provider['settings']['caldav_sync'], FILTER_VALIDATE_BOOLEAN)) {
                Caldav::sync((string) $provider['id']);
            }
        }
    }

    /**
     * Clean up old customer data based on data retention settings.
     *
     * Use this method in a cronjob to automatically delete customer data older than the configured retention period.
     *
     * Usage:
     *
     * php index.php console cleanup
     *
     * @throws Exception
     */
    public function cleanup(): void
    {
        $this->cleanup->run();
    }

    /**
     * Send SMS and WhatsApp reminders for all appointments scheduled tomorrow.
     *
     * Use this method in a cronjob to automatically remind customers about upcoming
     * appointments via SMSO.ro and Flaxxa WAPI. Runs once per day (ideally at 18:00
     * Europe/Bucharest); selects every appointment whose start_datetime falls in the
     * next calendar day in the provider timezone and has not been reminded yet.
     *
     * Usage:
     *
     * php index.php console send_sms_reminders
     */
    public function send_sms_reminders(): void
    {
        // Use the provider/business timezone so "tomorrow" is a real calendar day
        // for the clinic, not a UTC day. Europe/Bucharest is the current production
        // timezone; falling back to the PHP default keeps local dev working.
        $timezone = new DateTimeZone('Europe/Bucharest');

        try {
            $timezone = new DateTimeZone(setting('default_timezone') ?: 'Europe/Bucharest');
        } catch (Throwable $e) {
            log_message('warning', '[SMSO] Invalid default timezone, using Europe/Bucharest: ' . $e->getMessage());
        }

        $now = new DateTime('now', $timezone);
        $tomorrow = (clone $now)->modify('+1 day')->setTime(0, 0, 0);
        $endOfTomorrow = (clone $tomorrow)->setTime(23, 59, 59);

        $from = $tomorrow->format('Y-m-d H:i:s');
        $until = $endOfTomorrow->format('Y-m-d H:i:s');

        // Exclude cancelled/draft/no-show statuses. The list covers both the provider
        // values and the client-self-service constants.
        $excludedStatuses = [
            'Anulat',
            APPOINTMENT_STATUS_CANCELLED_BY_CLIENT,
            'Schita',
            'Nu s-a prezentat',
        ];

        $appointments = $this->appointments_model->get_pending_sms_reminders($from, $until, $excludedStatuses);

        $groups = group_appointments_same_day_chain(
            $appointments,
            same_day_group_gap_minutes(),
            same_day_group_excluded_statuses(),
        );

        foreach ($groups as $group) {
            $appointment = $group[0];
            $group_count = count($group);

            // Ensure the leading appointment has a token for the self-service link.
            if (empty($appointment['confirmation_token'])) {
                $appointment['confirmation_token'] = $this->appointments_model->regenerate_confirmation_token(
                    $appointment['id'],
                );
            }

            // Back-fill tokens for the other group members so future runs can reference them.
            foreach ($group as $member) {
                if ((int) $member['id'] !== (int) $appointment['id'] && empty($member['confirmation_token'])) {
                    $this->appointments_model->regenerate_confirmation_token($member['id']);
                }
            }

            try {
                $customer = $this->customers_model->find($appointment['id_users_customer']);
            } catch (Throwable $e) {
                log_message('debug', '[SMSO] Reminder skipped: customer not found for appointment #' . $appointment['id']);
                continue;
            }

            if (empty($customer['phone_number'])) {
                log_message('debug', '[SMSO] Reminder skipped: no phone for customer #' . $customer['id']);
                $this->mark_reminder_attempted_for_group($group, 'no phone');
                continue;
            }

            // Load the provider so the reminder date/time is formatted in the provider's timezone.
            $provider = [];
            try {
                $provider = $this->providers_model->find($appointment['id_users_provider']);
            } catch (Throwable $e) {
                log_message('error', '[SMSO] Could not load provider for appointment #' . $appointment['id'] . ': ' . $e->getMessage());
            }

            // Resolve the (possibly concatenated) service name used by the reminder body.
            $service = ['name' => '-'];
            try {
                if ($group_count > 1) {
                    $service_ids = array_column($group, 'id_services');
                    $rows = $this->db
                        ->select('id, name')
                        ->where_in('id', $service_ids)
                        ->get('services')
                        ->result_array();

                    $name_by_id = [];
                    foreach ($rows as $row) {
                        $name_by_id[(int) $row['id']] = $row['name'];
                    }

                    $names = [];
                    foreach ($group as $member) {
                        $names[] = $name_by_id[(int) $member['id_services']] ?? '';
                    }

                    $service['name'] = implode(' + ', array_filter($names));
                } else {
                    $loaded = $this->services_model->find($appointment['id_services']);
                    $service['name'] = $loaded['name'] ?? '-';
                }
            } catch (Throwable $e) {
                log_message('error', '[wa-flaxxa] Could not resolve service name(s) for appointment #' . $appointment['id'] . ': ' . $e->getMessage());
            }

            // Single and grouped SMS reminders both mention the procedure(s).
            $sms_service = $service;

            $sms_error = null;
            try {
                $this->sms_smso->send_reminder($appointment, $customer, $provider, $sms_service);
            } catch (Throwable $e) {
                $sms_error = $e->getMessage();
                log_message('error', '[SMSO] Reminder exception for appointment #' . $appointment['id'] . ': ' . $sms_error);
            }

            try {
                $this->whatsapp_flaxxa->send_reminder($appointment, $customer, $service, $provider);
            } catch (Throwable $e) {
                log_message('error', '[wa-flaxxa] Reminder exception for appointment #' . $appointment['id'] . ': ' . $e->getMessage());
            }

            if ($group_count > 1) {
                log_message('debug', '[wa-flaxxa] Grouped reminder for customer ' . ($customer['id'] ?? 'N/A') . ' — ' . $group_count . ' appointments');
            }

            $this->mark_reminder_attempted_for_group($group, $sms_error);
        }

        log_message('debug', '[SMSO] Reminder run finished. Checked ' . count($appointments) . ' appointment(s).');
    }

    /**
     * Mark an appointment as having received (or attempted) an SMS reminder.
     *
     * @param int $appointmentId Appointment ID.
     * @param string|null $error Optional error message if the reminder failed.
     */
    private function markReminderAttempted(int $appointmentId, ?string $error = null): void
    {
        $data = [
            'reminder_sent_at' => date('Y-m-d H:i:s'),
        ];

        if ($error !== null) {
            $data['sms_reminder_error'] = substr($error, 0, 512);
        }

        $this->db->update('appointments', $data, ['id' => $appointmentId]);
    }

    /**
     * Mark every appointment in a reminder group as attempted.
     *
     * Prevents a grouped reminder from being re-sent for each member on a later run.
     *
     * @param array $group Group of appointments (need an 'id' key each).
     * @param string|null $error Optional error message if the reminder failed.
     */
    private function mark_reminder_attempted_for_group(array $group, ?string $error = null): void
    {
        foreach ($group as $appointment) {
            $this->markReminderAttempted((int) $appointment['id'], $error);
        }
    }

    /**
     * Process appointments whose deposit payment link was sent more than 24 hours
     * ago and is still unpaid: auto-cancel the appointment (recoverable 'Anulat'
     * status, same as a manual cancel from the staff modal) and notify the
     * CUSTOMER on WhatsApp. Each appointment is processed at most once.
     *
     * Usage:
     *
     * php index.php console process_unpaid_deposits
     */
    public function process_unpaid_deposits(): void
    {
        // Housekeeping: purge short payment links older than the 7-day
        // retention window (click history is kept for a week).
        $purged_links = $this->payment_links_model->purge_expired();

        if ($purged_links > 0) {
            log_message('debug', '[unpaid-deposit-cancel] Purged ' . $purged_links . ' old payment link(s).');
        }

        // Use the business timezone, same as the reminder job.
        $timezone = new DateTimeZone('Europe/Bucharest');

        try {
            $timezone = new DateTimeZone(setting('default_timezone') ?: 'Europe/Bucharest');
        } catch (Throwable $e) {
            log_message('warning', '[unpaid-deposit-cancel] Invalid default timezone, using Europe/Bucharest: ' . $e->getMessage());
        }

        $now = new DateTime('now', $timezone);
        $threshold = (clone $now)->modify('-24 hours')->format('Y-m-d H:i:s');

        if (empty($this->readEnvOrConfig('CLIENT_CANCEL_TEMPLATE_NAME'))) {
            log_message('warning', '[unpaid-deposit-cancel] CLIENT_CANCEL_TEMPLATE_NAME is not configured; skipping run.');

            return;
        }

        // Exclude already cancelled/draft/no-show statuses (same list as the reminder job).
        $excludedStatuses = [
            'Anulat',
            APPOINTMENT_STATUS_CANCELLED_BY_CLIENT,
            'Schita',
            'Nu s-a prezentat',
        ];

        $appointments = $this->appointments_model->get_pending_unpaid_deposit_alerts(
            $threshold,
            $now->format('Y-m-d H:i:s'),
            $excludedStatuses,
        );

        foreach ($appointments as $appointment) {
            try {
                // Race safety: re-fetch and re-check immediately before acting so a
                // deposit paid (or already processed) between the query and now is
                // never auto-cancelled.
                $fresh = $this->appointments_model->find($appointment['id']);

                if (
                    empty($fresh)
                    || ($fresh['deposit_status'] ?? 'none') !== 'unpaid'
                    || !empty($fresh['deposit_unpaid_alerted_at'])
                    || in_array($fresh['status'], $excludedStatuses, true)
                ) {
                    log_message('debug', '[unpaid-deposit-cancel] Appointment #' . $appointment['id'] . ' no longer eligible; skipping.');

                    continue;
                }

                // 1) Auto-cancel via the standard, recoverable status change — the
                // exact same mechanism the staff modal uses (status 'Anulat' +
                // appointments_model save). NOT a hard-delete; the manager can
                // revert it from the modal status dropdown.
                $fresh['status'] = 'Anulat';
                $fresh['deposit_unpaid_alerted_at'] = date('Y-m-d H:i:s');

                $this->appointments_model->save($fresh);

                log_message('debug', '[unpaid-deposit-cancel] Appointment #' . $fresh['id'] . ' auto-cancelled (unpaid deposit > 24h).');

                // 2) Notify the customer on WhatsApp (failure is logged but does
                // not roll back the cancellation; the appointment stays excluded
                // from future runs via deposit_unpaid_alerted_at + status 'Anulat').
                $customer = [];
                $service = [];
                $provider = [];

                try {
                    $customer = $this->customers_model->find($fresh['id_users_customer']);
                    $service = $this->services_model->find($fresh['id_services']);
                    $provider = $this->providers_model->find($fresh['id_users_provider']);
                } catch (Throwable $e) {
                    log_message('error', '[unpaid-deposit-cancel] Could not load relations for appointment #' . $fresh['id'] . ': ' . $e->getMessage());
                }

                $result = $this->whatsapp_flaxxa->send_appointment_cancelled_unpaid(
                    $fresh,
                    $customer,
                    $service,
                    $provider,
                );

                if ($result['success']) {
                    log_message('debug', '[unpaid-deposit-cancel] Customer notified for appointment #' . $fresh['id']);
                } else {
                    log_message('error', '[unpaid-deposit-cancel] Customer notification failed for appointment #' . $fresh['id'] . ': ' . ($result['error'] ?? 'unknown'));
                }
            } catch (Throwable $e) {
                // Non-blocking: one failing appointment must not stop the run.
                log_message('error', '[unpaid-deposit-cancel] Exception for appointment #' . ($appointment['id'] ?? 'N/A') . ': ' . $e->getMessage());
            }
        }

        log_message('debug', '[unpaid-deposit-cancel] Run finished. Checked ' . count($appointments) . ' appointment(s).');
    }

    /**
     * Read a value from an environment variable or from the Config class.
     *
     * Mirrors Whatsapp_flaxxa::readEnvOrConfig().
     *
     * @param string $name The environment variable / Config constant name.
     *
     * @return string|null
     */
    private function readEnvOrConfig(string $name): ?string
    {
        $value = getenv($name);

        if ($value !== false && $value !== '') {
            return $value;
        }

        if (defined("Config::$name")) {
            $value = constant("Config::$name");
            return $value !== '' ? (string) $value : null;
        }

        return null;
    }

    /**
     * Show help information about the console capabilities.
     *
     * Use this method to see the available commands.
     *
     * Usage:
     *
     * php index.php console help
     */
    public function help(): void
    {
        $help = [
            '',
            'Easy!Appointments ' . config('version'),
            '',
            'Usage:',
            '',
            '⇾ php index.php console [command] [arguments]',
            '',
            'Commands:',
            '',
            '⇾ php index.php console bootstrap      (create/update super-admin from env vars)',
            '⇾ php index.php console migrate',
            '⇾ php index.php console migrate fresh',
            '⇾ php index.php console migrate up',
            '⇾ php index.php console migrate down',
            '⇾ php index.php console seed',
            '⇾ php index.php console install',
            '⇾ php index.php console backup',
            '⇾ php index.php console sync',
            '⇾ php index.php console cleanup        (cleans sessions, logs, cache, and customer data)',
            '⇾ php index.php console send_sms_reminders  (sends ~24h SMS reminders via SMSO.ro)',
            '⇾ php index.php console process_unpaid_deposits  (auto-cancels deposits unpaid after 24h + notifies the customer)',
            '',
            '',
        ];

        response(implode(PHP_EOL, $help));
    }
}
