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
     *
     * @return void
     */
    public function send_confirmation(array $appointment, array $customer): void
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

            $message = $this->buildConfirmationMessage($appointment);

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
     *
     * @return string
     */
    private function buildConfirmationMessage(array $appointment): string
    {
        $start = $appointment['start_datetime'] ?? null;

        if (empty($start)) {
            $date = '-';
            $time = '-';
        } else {
            $date = date('d.m.Y', strtotime($start));
            $time = date('H:i', strtotime($start));
        }

        return 'Buna ziua! Programarea dvs. la BodySculpt pe ' . $date . ' la ' . $time
            . ' a fost confirmata. Va asteptam!';
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
        log_message('info', '[SMSO] ' . $message);
    }
}
