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
     * Send SMS and WhatsApp reminders for appointments that are ~24 hours away.
     *
     * Use this method in a cronjob to automatically remind customers about upcoming
     * appointments via SMSO.ro and Flaxxa WAPI. Runs hourly on Railway; selects appointments
     * between 23 and 25 hours from now that have not been reminded yet.
     *
     * Usage:
     *
     * php index.php console send_sms_reminders
     */
    public function send_sms_reminders(): void
    {
        $now = new DateTime();

        // Hourly cron: look 23-25 hours ahead so every appointment in the 24h window
        // gets picked up exactly once, even with small platform timing drifts.
        $from = (clone $now)->modify('+23 hours')->format('Y-m-d H:i:s');
        $until = (clone $now)->modify('+25 hours')->format('Y-m-d H:i:s');

        $excludedStatuses = ['Anulat', 'Schita', 'Nu s-a prezentat'];

        $appointments = $this->appointments_model->get_pending_sms_reminders($from, $until, $excludedStatuses);

        foreach ($appointments as $appointment) {
            if (empty($appointment['confirmation_token'])) {
                $appointment['confirmation_token'] = $this->appointments_model->regenerate_confirmation_token(
                    $appointment['id'],
                );
            }

            try {
                $customer = $this->customers_model->find($appointment['id_users_customer']);
            } catch (Throwable $e) {
                log_message('debug', '[SMSO] Reminder skipped: customer not found for appointment #' . $appointment['id']);
                continue;
            }

            if (empty($customer['phone_number'])) {
                log_message('debug', '[SMSO] Reminder skipped: no phone for customer #' . $customer['id']);
                $this->markReminderAttempted($appointment['id'], 'no phone');
                continue;
            }

            // Load the provider so the reminder date/time is formatted in the provider's timezone.
            $provider = [];
            try {
                $provider = $this->providers_model->find($appointment['id_users_provider']);
            } catch (Throwable $e) {
                log_message('error', '[SMSO] Could not load provider for appointment #' . $appointment['id'] . ': ' . $e->getMessage());
            }

            try {
                $this->sms_smso->send_reminder($appointment, $customer, $provider);
                $this->markReminderAttempted($appointment['id']);
            } catch (Throwable $e) {
                log_message('error', '[SMSO] Reminder exception for appointment #' . $appointment['id'] . ': ' . $e->getMessage());
                $this->markReminderAttempted($appointment['id'], $e->getMessage());
            }

            // Send WhatsApp reminder in parallel (failure isolated, does not affect SMS state).
            $service = [];
            try {
                $service = $this->services_model->find($appointment['id_services']);
            } catch (Throwable $e) {
                log_message('error', '[wa-flaxxa] Could not load service for appointment #' . $appointment['id'] . ': ' . $e->getMessage());
            }

            try {
                $this->whatsapp_flaxxa->send_reminder($appointment, $customer, $service, $provider);
            } catch (Throwable $e) {
                log_message('error', '[wa-flaxxa] Reminder exception for appointment #' . $appointment['id'] . ': ' . $e->getMessage());
            }
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
            'sms_reminder_sent_at' => date('Y-m-d H:i:s'),
        ];

        if ($error !== null) {
            $data['sms_reminder_error'] = substr($error, 0, 512);
        }

        $this->db->update('appointments', $data, ['id' => $appointmentId]);
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
            '',
            '',
        ];

        response(implode(PHP_EOL, $help));
    }
}
