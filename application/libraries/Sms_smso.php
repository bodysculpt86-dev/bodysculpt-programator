<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * SMSO.ro SMS sender library.
 * ---------------------------------------------------------------------------- */

/**
 * Class Sms_smso
 *
 * Sends SMS messages via the SMSO.ro REST API.
 */
class Sms_smso
{
    /**
     * @var string SMSO API endpoint for sending messages.
     */
    private const API_URL = 'https://app.smso.ro/api/v1/send';

    /**
     * @var CI_Controller|object CodeIgniter instance.
     */
    protected $CI;

    /**
     * @var string|null Cached API key.
     */
    protected ?string $apiKey = null;

    /**
     * @var string|null Cached sender ID.
     */
    protected ?string $senderId = null;

    /**
     * @var bool Whether the library is running in log-only mode.
     */
    protected bool $logOnly = false;

    /**
     * Sms_smso constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->apiKey = $this->readEnvOrConfig('SMSO_API_KEY');
        $this->senderId = $this->readEnvOrConfig('SMSO_SENDER_ID');

        // LOG_ONLY mode lets you test the integration without spending real SMS credit.
        if (strtoupper($this->apiKey ?? '') === 'LOG_ONLY') {
            $this->logOnly = true;
        }

        $this->CI->load->helper('phone');
    }

    /**
     * Read a value from an environment variable or from the Config class.
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
     * Send a confirmation SMS for an appointment.
     *
     * The method is failure-isolated: it catches all exceptions and logs them,
     * it never propagates errors to the caller.
     *
     * @param array $appointment Appointment data (must contain start_datetime).
     * @param array $customer Customer data (must contain phone_number and id).
     * @param array $service Service data (must contain name).
     * @param array|null $provider Provider data (must contain timezone). When provided,
     *                            the appointment date/time is formatted in this timezone.
     *
     * @return void
     */
    public function send_confirmation(array $appointment, array $customer, array $service, ?array $provider = null): void
    {
        try {
            $rawPhone = $customer['phone_number'] ?? null;
            $customerId = $customer['id'] ?? null;

            $normalizedPhone = normalize_romanian_phone($rawPhone);

            if ($normalizedPhone === null) {
                $this->log(
                    'SMS skipped: invalid phone for customer #' . ($customerId ?? 'N/A') . ': ' . ($rawPhone ?: '(empty)')
                );
                return;
            }

            $message = $this->buildConfirmationMessage($appointment, $customer, $service, $provider);

            if ($this->logOnly) {
                $this->log(
                    'LOG_ONLY SMS would send to ' . $normalizedPhone . ': ' . $message
                );
                return;
            }

            if (empty($this->apiKey) || empty($this->senderId)) {
                $this->log('SMS skipped: SMSO_API_KEY or SMSO_SENDER_ID not configured.');
                return;
            }

            $this->send($normalizedPhone, $message);
        } catch (Throwable $e) {
            $this->log(
                'SMS failed for customer #' . ($customer['id'] ?? 'N/A') . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * Build the Romanian confirmation message text (no diacritics, 1 SMS segment).
     *
     * @param array $appointment Appointment data.
     * @param array $customer Customer data.
     * @param array $service Service data.
     * @param array|null $provider Provider data (timezone source).
     *
     * @return string
     */
    private function buildConfirmationMessage(array $appointment, array $customer, array $service, ?array $provider = null): string
    {
        [$date, $time] = $this->formatDateTime($appointment, $provider);

        $firstName = $customer['first_name'] ?? '';
        $lastName = $customer['last_name'] ?? '';
        $name = trim($firstName . ' ' . $lastName);

        if ($name === '') {
            $name = 'client';
        }

        $serviceName = $service['name'] ?? '-';

        $message = 'Buna, ' . $name . ', programarea ta din data de ' . $date . ', ora ' . $time
            . ' la procedura ' . $serviceName . ' a fost stabilita. Te asteptam in locatia noastra din str. Berzei nr. 16, la Body Sculpt Clinique. Zi frumoasa!';

        return $this->removeDiacritics($message);
    }

    /**
     * Format the appointment date/time in the provider's timezone.
     *
     * @param array $appointment Appointment data.
     * @param array|null $provider Provider data (timezone source).
     *
     * @return array [date, time]
     */
    private function formatDateTime(array $appointment, ?array $provider = null): array
    {
        $start = $appointment['start_datetime'] ?? null;

        if (empty($start)) {
            return ['-', '-'];
        }

        $timezone = !empty($provider['timezone']) ? $provider['timezone'] : date_default_timezone_get();

        try {
            $dateTime = new DateTime($start, new DateTimeZone($timezone));
        } catch (Throwable $e) {
            $this->log('Date formatting failed: ' . $e->getMessage());
            return ['-', '-'];
        }

        $day = (int) $dateTime->format('j');
        $month = (int) $dateTime->format('n');
        $romanianMonths = [
            1 => 'Ianuarie',
            2 => 'Februarie',
            3 => 'Martie',
            4 => 'Aprilie',
            5 => 'Mai',
            6 => 'Iunie',
            7 => 'Iulie',
            8 => 'August',
            9 => 'Septembrie',
            10 => 'Octombrie',
            11 => 'Noiembrie',
            12 => 'Decembrie',
        ];
        $date = $day . ' ' . ($romanianMonths[$month] ?? $dateTime->format('F'));
        $time = $dateTime->format('H:i');

        return [$date, $time];
    }

    /**
     * Send a reminder SMS ~24 hours before an appointment.
     *
     * Reuses the same failure isolation as send_confirmation.
     *
     * @param array $appointment Appointment data (must contain start_datetime).
     * @param array $customer Customer data (must contain phone_number and id).
     * @param array|null $provider Provider data (must contain timezone). When provided,
     *                            the appointment date/time is formatted in this timezone.
     *
     * @return void
     */
    public function send_reminder(array $appointment, array $customer, ?array $provider = null): void
    {
        try {
            $rawPhone = $customer['phone_number'] ?? null;
            $customerId = $customer['id'] ?? null;

            $normalizedPhone = normalize_romanian_phone($rawPhone);

            if ($normalizedPhone === null) {
                $this->log(
                    'Reminder SMS skipped: invalid phone for customer #' . ($customerId ?? 'N/A') . ': ' . ($rawPhone ?: '(empty)')
                );
                return;
            }

            $message = $this->buildReminderMessage($appointment, $provider);

            if ($this->logOnly) {
                $this->log(
                    'LOG_ONLY reminder SMS would send to ' . $normalizedPhone . ': ' . $message
                );
                return;
            }

            if (empty($this->apiKey) || empty($this->senderId)) {
                $this->log('Reminder SMS skipped: SMSO_API_KEY or SMSO_SENDER_ID not configured.');
                return;
            }

            $this->send($normalizedPhone, $message);
        } catch (Throwable $e) {
            $this->log(
                'Reminder SMS failed for customer #' . ($customer['id'] ?? 'N/A') . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * Build the Romanian reminder message text (no diacritics, 1 SMS segment).
     *
     * @param array $appointment Appointment data.
     * @param array|null $provider Provider data (timezone source).
     *
     * @return string
     */
    private function buildReminderMessage(array $appointment, ?array $provider = null): string
    {
        [$date, $time] = $this->formatDateTime($appointment, $provider);

        $message = 'Reminder: aveti programare la BodySculpt maine, ' . $date . ' la ' . $time . '. Va asteptam!';

        if (!empty($appointment['confirmation_token'])) {
            $message .= ' Confirmi/anulezi: ' . base_url('p/' . $appointment['confirmation_token']);
        }

        return $this->removeDiacritics($message);
    }

    /**
     * Remove Romanian/Western European diacritics from a string.
     *
     * Keeps the SMS cheap by ensuring plain ASCII output.
     *
     * @param string $text Input text.
     *
     * @return string
     */
    private function removeDiacritics(string $text): string
    {
        $replacements = [
            // Lowercase
            'ă' => 'a', 'â' => 'a', 'á' => 'a', 'à' => 'a', 'ä' => 'a', 'ã' => 'a',
            'î' => 'i', 'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'ĩ' => 'i',
            'ș' => 's', 'ş' => 's', 'ś' => 's',
            'ț' => 't', 'ţ' => 't', 'ť' => 't',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ẽ' => 'e',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ũ' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y',
            // Uppercase
            'Ă' => 'A', 'Â' => 'A', 'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Ã' => 'A',
            'Î' => 'I', 'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Ĩ' => 'I',
            'Ș' => 'S', 'Ş' => 'S', 'Ś' => 'S',
            'Ț' => 'T', 'Ţ' => 'T', 'Ť' => 'T',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ẽ' => 'E',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ũ' => 'U',
            'Ñ' => 'N', 'Ç' => 'C', 'Ý' => 'Y', 'Ÿ' => 'Y',
        ];

        return strtr($text, $replacements);
    }

    /**
     * Perform the HTTP POST to SMSO.ro.
     *
     * @param string $to Normalized phone number (E.164 without +).
     * @param string $body Message body.
     *
     * @return void
     *
     * @throws Exception If the API returns an error.
     */
    private function send(string $to, string $body): void
    {
        $ch = curl_init(self::API_URL);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'to' => $to,
            'sender' => $this->senderId,
            'body' => $body,
            'type' => 'transactional',
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Authorization: ' . $this->apiKey,
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('cURL error: ' . $curlError);
        }

        $decoded = json_decode($response, true);

        if ($httpCode !== 200 || empty($decoded['status']) || $decoded['status'] != 200) {
            $errorMessage = $decoded['message'] ?? $decoded['error'] ?? $response;
            throw new Exception('SMSO API error (HTTP ' . $httpCode . '): ' . $errorMessage);
        }

        $this->log('SMS sent to ' . $to . ', token: ' . ($decoded['responseToken'] ?? 'N/A'));
    }

    /**
     * Write a message to the application log.
     *
     * @param string $message Message to log.
     *
     * @return void
     */
    private function log(string $message): void
    {
        log_message('debug', '[SMSO] ' . $message);
    }
}
