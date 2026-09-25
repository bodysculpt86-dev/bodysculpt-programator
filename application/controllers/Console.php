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
        $this->load->library('telegram');

        $this->load->model('admins_model');
        $this->load->model('appointments_model');
        $this->load->model('customers_model');
        $this->load->model('payment_links_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
    }

    /**
     * Send the two appointment WhatsApp templates to a single number, as a
     * delivery test for the WhatsApp provider.
     *
     * The provider defaults to 'meta' rather than to whatever WA_PROVIDER says,
     * because the reason to run this is to prove the Graph API path works
     * *before* switching WA_PROVIDER to it — a test that followed the flag could
     * only ever test the provider already in use.
     *
     * The messages are real and go to a real WhatsApp number.
     *
     * Usage:
     *
     * php index.php console wa_test_send 40712345678
     * php index.php console wa_test_send 40712345678 flaxxa
     *
     * @param string $phone Recipient, international format, with or without '+'.
     * @param string $provider 'meta' (default) or 'flaxxa'.
     *
     * @return void
     */
    public function wa_test_send(string $phone = '', string $provider = 'meta'): void
    {
        $phone = trim($phone);

        if ($phone === '') {
            response('Usage: php index.php console wa_test_send <phone> [meta|flaxxa]');

            return;
        }

        $normalized = normalize_international_phone($phone);

        if ($normalized === null) {
            response('Not a usable phone number: ' . $phone);

            return;
        }

        // A separate instance with an explicit provider: the test must neither
        // depend on, nor disturb, the provider the application itself is using.
        // CodeIgniter re-instantiates the class when given a different object
        // name, so the already-loaded $this->whatsapp_flaxxa is left alone.
        $this->load->library('whatsapp_flaxxa', ['provider' => $provider], 'wa_test_sender');
        $sender = $this->wa_test_sender;

        $templates = $sender->get_configured_templates();

        $lines = [
            'Provider: ' . $sender->get_provider(),
            'Number:   +' . $normalized,
        ];

        $reason = $sender->get_log_only_reason();
        if ($reason !== null) {
            // Log-only mode is the one failure that looks identical to success
            // from the outside, so it is said here rather than left to the log.
            $lines[] = 'LOG_ONLY — nothing will actually be sent: ' . $reason;
        }

        // Placeholder data on purpose: this tests delivery and the shape of the
        // header/body components, not the content, and requiring a real
        // appointment to exist would make the test impossible to run on demand.
        $customer = [
            'id' => 0,
            'first_name' => 'Test',
            'last_name' => 'Revclar',
            'phone_number' => '+' . $normalized,
        ];

        $appointment = [
            'id' => 0,
            'start_datetime' => (new DateTime('tomorrow 14:30', new DateTimeZone('Europe/Bucharest')))
                ->format('Y-m-d H:i:s'),
        ];

        $service = ['name' => 'Test Revclar'];
        $provider_row = ['timezone' => 'Europe/Bucharest'];

        $lines[] = '';

        $confirmation = $sender->send_confirmation($appointment, $customer, $service, $provider_row);
        $lines[] = sprintf(
            '%-13s %-28s %s',
            'confirmation',
            $templates['confirmation'] ?? '(not configured)',
            $this->describe_wa_test_result($confirmation)
        );

        $reminder = $sender->send_reminder($appointment, $customer, $service, $provider_row);
        $lines[] = sprintf(
            '%-13s %-28s %s',
            'reminder',
            $templates['reminder'] ?? '(not configured)',
            $this->describe_wa_test_result($reminder)
        );

        $lines[] = '';
        // 'log_only' counts as success in the result array (the caller asked for
        // no send), so it is excluded explicitly here — otherwise a log-only run
        // would end by telling the operator to check a phone that was never sent to.
        $confirmation_sent = !empty($confirmation['success']) && empty($confirmation['log_only']);
        $reminder_sent = !empty($reminder['success']) && empty($reminder['log_only']);

        $lines[] = $confirmation_sent && $reminder_sent
            ? 'Both accepted. Check the phone — then set WA_PROVIDER=meta.'
            : 'Not both accepted. Nothing was changed; the app still sends via its configured provider.';

        response(implode(PHP_EOL, $lines));
    }

    /**
     * Render one wa_test_send result as a single line.
     *
     * @param array $result Result array from a Whatsapp_flaxxa sender.
     *
     * @return string
     */
    private function describe_wa_test_result(array $result): string
    {
        if (!empty($result['log_only'])) {
            return 'NOT SENT (log-only)';
        }

        if (!empty($result['success'])) {
            return 'accepted by the API';
        }

        return 'FAILED: ' . ($result['error'] ?? 'unknown error');
    }

    /**
     * Send one alert through the Telegram channel, to prove it arrives.
     *
     * The reminder alert is only worth having if the channel behind it is known to
     * work — the same reason wa_test_send exists for WhatsApp, and the same failure
     * it guards against: a notifier that is silently unconfigured looks exactly
     * like a run in which nothing went wrong. The notifier's own verdict is printed
     * rather than a generic success, so a missing variable is visible here.
     *
     * Usage:
     *
     * php index.php console telegram_test
     *
     * @return void
     */
    public function telegram_test(): void
    {
        try {
            $result = $this->telegram->send('[BodySculpt] test alertă — canalul Telegram funcționează.');
        } catch (Throwable $e) {
            response('Telegram library could not be used: ' . $e->getMessage());

            return;
        }

        if (!empty($result['sent'])) {
            response('Sent. Check the Telegram chat.');

            return;
        }

        response('NOT sent (' . ($result['reason'] ?? 'unknown') . '): ' . ($result['detail'] ?? ''));
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
     * Send ~24h appointment reminders via SMS + WhatsApp.
     *
     * Runs once per day (ideally at 18:00 Europe/Bucharest); selects every
     * appointment whose start_datetime falls in the next calendar day in the
     * provider timezone and has not yet delivered on either channel.
     *
     * Usage:
     *
     * php index.php console send_sms_reminders
     * php index.php console send_sms_reminders --dry-run
     *
     * --dry-run selects and prints exactly what a real run would process — the
     * appointment group, its recipient, and its current attempt count — without
     * sending anything, writing to the database, or raising a Telegram alert. Use
     * it to check this command against production data before trusting it to
     * actually retry / give up / alert.
     *
     * @param string $mode '--dry-run', or empty for a real run.
     *
     * @return void
     */
    public function send_sms_reminders(string $mode = ''): void
    {
        if ($mode !== '' && $mode !== '--dry-run') {
            response('Usage: php index.php console send_sms_reminders [--dry-run]');

            return;
        }

        $dry_run = $mode === '--dry-run';

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

        if ($dry_run) {
            $this->dry_run_reminder_groups($groups);

            return;
        }

        // Appointments that end the run without having received anything after
        // hitting REMINDER_MAX_ATTEMPTS: either every channel failed on every
        // attempt, or the customer never had a usable phone. Collected so the run
        // raises one alert per appointment — on the attempt that gives up on it,
        // not on every attempt leading up to that.
        $problems = [];

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

                // No channel can be attempted, so the appointment must not be marked
                // as reminded — it would hide a customer nobody can reach. The
                // reason is written to both channel columns so it stays findable,
                // and the run reports it, but it is a data problem rather than a
                // send failure and is counted separately in the alert.
                $attempts = $this->mark_reminder_undeliverable_for_group($group, 'no_phone');

                if ($attempts >= REMINDER_MAX_ATTEMPTS) {
                    $problems[] = ['kind' => 'no_phone', 'id' => (int) $appointment['id']];
                }

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

            // Both libraries isolate their own failures and return a result array, so
            // these catches should never fire. They are kept because the alternative
            // is that an unexpected Throwable takes down the rest of the run, and
            // because a leg that produced no result at all must not be read as one
            // that succeeded.
            try {
                $sms_result = $this->sms_smso->send_reminder($appointment, $customer, $provider, $sms_service);
            } catch (Throwable $e) {
                log_message('error', '[SMSO] Reminder exception for appointment #' . $appointment['id'] . ': ' . $e->getMessage());
                $sms_result = ['success' => false, 'error' => $e->getMessage()];
            }

            try {
                $wa_result = $this->whatsapp_flaxxa->send_reminder($appointment, $customer, $service, $provider);
            } catch (Throwable $e) {
                log_message('error', '[wa-flaxxa] Reminder exception for appointment #' . $appointment['id'] . ': ' . $e->getMessage());
                $wa_result = ['success' => false, 'error' => $e->getMessage()];
            }

            if ($group_count > 1) {
                log_message('debug', '[wa-flaxxa] Grouped reminder for customer ' . ($customer['id'] ?? 'N/A') . ' — ' . $group_count . ' appointments');
            }

            $outcome = $this->mark_reminder_attempted_for_group($group, $sms_result, $wa_result);

            if (!$outcome['delivered'] && $outcome['attempts'] >= REMINDER_MAX_ATTEMPTS) {
                $problems[] = [
                    'kind' => 'failed',
                    'id' => (int) $appointment['id'],
                    'sms' => $sms_result['error'] ?? null,
                    'wa' => $wa_result['error'] ?? null,
                ];
            }
        }

        if ($problems !== []) {
            $this->alert_reminder_problems($problems, count($appointments));
        }

        log_message('debug', '[SMSO] Reminder run finished. Checked ' . count($appointments) . ' appointment(s).');
    }

    /**
     * Whether a channel really delivered a reminder.
     *
     * 'log_only' counts as success inside the sender libraries, because the caller
     * asked for no send — the same exclusion wa_test_send makes when it reports.
     * Here it has to be excluded: a log-only leg put nothing in front of anybody.
     *
     * @param array $result Result array from one of the sender libraries.
     *
     * @return bool
     */
    private function reminder_delivered(array $result): bool
    {
        return !empty($result['success']) && empty($result['log_only']);
    }

    /**
     * The error text to store for a channel that did not deliver.
     *
     * @param array $result Result array from one of the sender libraries.
     *
     * @return string
     */
    private function reminder_error_text(array $result): string
    {
        // Log-only is not a failure, but it is not a delivery either. Storing it as
        // "unknown error" would send the next reader hunting for a fault that is
        // really a configuration choice — and LOG_ONLY is exactly how the reminder
        // path gets dry-run before it is trusted.
        if (!empty($result['log_only'])) {
            return 'log_only';
        }

        $error = $result['error'] ?? null;

        if ($error === null || $error === '') {
            // A leg that failed without saying why must not be stored as an empty
            // string: NULL reads as "no error" to every later query.
            $error = 'unknown error';
        }

        return substr((string) $error, 0, 512);
    }

    /**
     * Record the outcome of both reminder channels for every member of a group.
     *
     * `reminder_sent_at` is what stops get_pending_sms_reminders() handing the
     * appointment to a later run, so it is written only when a channel actually
     * delivered. It used to be written unconditionally, which meant a WhatsApp
     * outage left every appointment stamped as reminded — never retried, and with
     * nothing in the database to say the message had not gone out.
     *
     * `reminder_attempts` is incremented for every member regardless of the
     * outcome; get_pending_sms_reminders() reads it back to cap retries at
     * REMINDER_MAX_ATTEMPTS.
     *
     * @param array $group Group of appointments (need 'id' and 'reminder_attempts' keys each).
     * @param array $sms_result Result array from Sms_smso::send_reminder().
     * @param array $wa_result Result array from Whatsapp_flaxxa::send_reminder().
     *
     * @return array{delivered: bool, attempts: int} Whether at least one channel
     *   delivered, and the leading appointment's new attempt count.
     */
    private function mark_reminder_attempted_for_group(array $group, array $sms_result, array $wa_result): array
    {
        $sms_sent = $this->reminder_delivered($sms_result);
        $wa_sent = $this->reminder_delivered($wa_result);
        $delivered = $sms_sent || $wa_sent;

        $leading_attempts = (int) ($group[0]['reminder_attempts'] ?? 0) + 1;

        foreach ($group as $appointment) {
            $data = [
                'sms_reminder_sent_at' => $sms_sent ? date('Y-m-d H:i:s') : null,
                'sms_reminder_error' => $sms_sent ? null : $this->reminder_error_text($sms_result),
                'wa_reminder_sent_at' => $wa_sent ? date('Y-m-d H:i:s') : null,
                'wa_reminder_error' => $wa_sent ? null : $this->reminder_error_text($wa_result),
                'reminder_attempts' => (int) ($appointment['reminder_attempts'] ?? 0) + 1,
            ];

            if ($delivered) {
                $data['reminder_sent_at'] = date('Y-m-d H:i:s');
            }

            $this->db->update('appointments', $data, ['id' => (int) $appointment['id']]);
        }

        return ['delivered' => $delivered, 'attempts' => $leading_attempts];
    }

    /**
     * Record that nothing could be attempted for a group, without marking it as reminded.
     *
     * `reminder_sent_at` is deliberately left alone so the appointment stays in the
     * pending set (until reminder_attempts hits the cap): it is only in the window
     * while it is still "tomorrow", and a phone number fixed within that window
     * should still produce a reminder.
     *
     * @param array $group Group of appointments (need 'id' and 'reminder_attempts' keys each).
     * @param string $reason Reason code stored on both channel columns.
     *
     * @return int The leading appointment's new attempt count.
     */
    private function mark_reminder_undeliverable_for_group(array $group, string $reason): int
    {
        $leading_attempts = (int) ($group[0]['reminder_attempts'] ?? 0) + 1;

        foreach ($group as $appointment) {
            $data = [
                'sms_reminder_sent_at' => null,
                'sms_reminder_error' => substr($reason, 0, 512),
                'wa_reminder_sent_at' => null,
                'wa_reminder_error' => substr($reason, 0, 512),
                'reminder_attempts' => (int) ($appointment['reminder_attempts'] ?? 0) + 1,
            ];

            $this->db->update('appointments', $data, ['id' => (int) $appointment['id']]);
        }

        return $leading_attempts;
    }

    /**
     * Print exactly what a real send_sms_reminders() run would process, without
     * sending anything, writing to the database, or raising a Telegram alert.
     *
     * @param array $groups Groups from group_appointments_same_day_chain().
     */
    private function dry_run_reminder_groups(array $groups): void
    {
        if ($groups === []) {
            response('[DRY RUN] No appointments pending a reminder.');

            return;
        }

        foreach ($groups as $group) {
            $appointment = $group[0];
            $attempts = (int) ($appointment['reminder_attempts'] ?? 0);

            try {
                $customer = $this->customers_model->find($appointment['id_users_customer']);
                $phone = $customer['phone_number'] ?? '(no phone)';
                $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
            } catch (Throwable $e) {
                $phone = '(customer not found)';
                $name = '';
            }

            $ids = implode(', ', array_map(static fn (array $a): string => '#' . $a['id'], $group));

            response(sprintf(
                '[DRY RUN] %s (%s) — appointment(s) %s — attempt %d/%d would be sent',
                $name !== '' ? $name : '(no name)',
                $phone,
                $ids,
                $attempts + 1,
                REMINDER_MAX_ATTEMPTS,
            ));
        }

        response('[DRY RUN] ' . count($groups) . ' group(s). Nothing sent, no database writes, no Telegram alert.');
    }

    /**
     * Raise one Telegram alert describing the appointments a reminder run gave up on.
     *
     * Callers only add an appointment to $problems once it has reached
     * REMINDER_MAX_ATTEMPTS without delivering, so each appointment can appear
     * here at most once, ever — the alert for it fires on the attempt that gives
     * up, not on every attempt leading up to that. Multiple appointments hitting
     * the cap in the same run still batch into one Telegram message, not one per
     * appointment, so a shared provider outage doesn't burst-fire the channel.
     *
     * @param array $problems Entries with 'kind' ('failed'|'no_phone') and 'id'.
     * @param int $checked How many appointments the run looked at.
     */
    private function alert_reminder_problems(array $problems, int $checked): void
    {
        $failed = [];
        $no_phone = 0;

        foreach ($problems as $problem) {
            if (($problem['kind'] ?? '') === 'no_phone') {
                $no_phone++;
            } else {
                $failed[] = $problem;
            }
        }

        $ids = array_map(static fn (array $problem): string => '#' . $problem['id'], $failed);

        $lines = [
            '[BodySculpt] ' . count($problems) . ' programare(i) NU au primit reminderul după ' . REMINDER_MAX_ATTEMPTS . ' încercări',
            'Rulă: ' . date('Y-m-d H:i') . ' — verificate: ' . $checked,
        ];

        if ($ids !== []) {
            $shown = array_slice($ids, 0, 20);
            $rest = count($ids) - count($shown);

            $lines[] = 'Programări: ' . implode(', ', $shown) . ($rest > 0 ? ' … +' . $rest : '');
        }

        // Distinct reasons only: a run that fails 30 times usually fails the same way.
        $reasons = [];

        foreach ($failed as $problem) {
            foreach (['sms' => 'SMS', 'wa' => 'WhatsApp'] as $key => $label) {
                $error = $problem[$key] ?? null;

                if ($error !== null && $error !== '') {
                    $reasons[$label . ': ' . $error] = true;
                }
            }
        }

        if ($reasons !== []) {
            $lines[] = '';

            foreach (array_slice(array_keys($reasons), 0, 3) as $reason) {
                $lines[] = '• ' . substr($reason, 0, 200);
            }
        }

        if ($no_phone > 0) {
            $lines[] = '';
            $lines[] = 'Fără telefon (niciun canal nu a putut fi încercat): ' . $no_phone;
        }

        $lines[] = '';
        $lines[] = 'Nu au fost marcate ca trimise. Nu vor mai fi reîncercate automat — necesită verificare manuală.';

        try {
            $result = $this->telegram->send(implode(PHP_EOL, $lines));

            if (!empty($result['sent'])) {
                log_message('debug', '[telegram] Reminder alert sent (' . count($problems) . ' problem(s)).');

                return;
            }

            log_message(
                'error',
                '[telegram] Reminder alert NOT sent (' . ($result['reason'] ?? 'unknown') . '): ' . ($result['detail'] ?? '')
            );
        } catch (Throwable $e) {
            // An alert channel that can abort the run it is reporting on would be
            // worse than no alert channel at all.
            log_message('error', '[telegram] Reminder alert threw: ' . $e->getMessage());
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
            '⇾ php index.php console send_sms_reminders [--dry-run]  (sends ~24h SMS + WhatsApp reminders, capped at ' . REMINDER_MAX_ATTEMPTS . ' attempts; alerts on Telegram once an appointment gives up)',
            '⇾ php index.php console process_unpaid_deposits  (auto-cancels deposits unpaid after 24h + notifies the customer)',
            '⇾ php index.php console wa_test_send 40712345678 [meta|flaxxa]  (sends the two appointment templates to one number)',
            '⇾ php index.php console telegram_test  (sends one alert to the configured Telegram chat)',
            '',
            '',
        ];

        response(implode(PHP_EOL, $help));
    }
}
