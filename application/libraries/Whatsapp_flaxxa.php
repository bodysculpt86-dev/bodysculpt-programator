<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Flaxxa WAPI WhatsApp sender library.
 * ---------------------------------------------------------------------------- */

/**
 * Class Whatsapp_flaxxa
 *
 * Sends WhatsApp template messages via the Flaxxa WAPI REST API.
 */
class Whatsapp_flaxxa
{
    /**
     * @var string Flaxxa WAPI endpoint for sending template messages.
     */
    private const API_URL = 'https://wapi.flaxxa.com/api/v1/sendtemplatemessage';

    /**
     * @var CI_Controller|object CodeIgniter instance.
     */
    protected $CI;

    /**
     * @var string|null Cached Flaxxa API token.
     */
    protected ?string $apiToken = null;

    /**
     * @var string|null Cached confirmation template name.
     */
    protected ?string $confirmationTemplate = null;

    /**
     * @var string|null Cached reminder template name.
     */
    protected ?string $reminderTemplate = null;

    /**
     * @var string|null Cached marketing template name.
     */
    protected ?string $marketingTemplate = null;

    /**
     * @var string Cached template language code.
     */
    protected string $templateLanguage = 'ro';

    /**
     * @var bool Whether the library is running in log-only mode.
     */
    protected bool $logOnly = false;

    /**
     * Whatsapp_flaxxa constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->apiToken = $this->readEnvOrConfig('FLAXXA_API_TOKEN');
        $this->confirmationTemplate = $this->readEnvOrConfig('FLAXXA_CONFIRMATION_TEMPLATE');
        $this->reminderTemplate = $this->readEnvOrConfig('FLAXXA_REMINDER_TEMPLATE');
        $this->marketingTemplate = $this->readEnvOrConfig('FLAXXA_MARKETING_TEMPLATE');

        $language = $this->readEnvOrConfig('FLAXXA_TEMPLATE_LANGUAGE');
        if (!empty($language)) {
            $this->templateLanguage = $language;
        }

        // LOG_ONLY mode lets you test the integration without sending real WhatsApp messages.
        if (empty($this->apiToken) || strtoupper($this->apiToken) === 'LOG_ONLY') {
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
     * Send a confirmation WhatsApp message for a new appointment.
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

            $phone = $this->normalizePhoneForWhatsapp($rawPhone);

            if ($phone === null) {
                $this->log(
                    'Confirmation skipped: invalid phone for customer #' . ($customerId ?? 'N/A') . ': ' . ($rawPhone ?: '(empty)')
                );
                return;
            }

            if (empty($this->confirmationTemplate)) {
                $this->log('Confirmation skipped: FLAXXA_CONFIRMATION_TEMPLATE not configured.');
                return;
            }

            $components = [
                $this->buildHeaderComponent($customer),
                $this->buildConfirmationBody($appointment, $service, $provider),
            ];

            if ($this->logOnly) {
                $this->log(
                    'LOG_ONLY confirmation would send to ' . $phone . ' using template "' . $this->confirmationTemplate . '": ' . json_encode($components)
                );
                return;
            }

            $this->send($phone, $this->confirmationTemplate, $components);
        } catch (Throwable $e) {
            $this->log(
                'Confirmation failed for customer #' . ($customer['id'] ?? 'N/A') . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * Send a reminder WhatsApp message ~24 hours before an appointment.
     *
     * Reuses the same failure isolation as send_confirmation.
     *
     * @param array $appointment Appointment data (must contain start_datetime).
     * @param array $customer Customer data (must contain phone_number and id).
     * @param array $service Service data (must contain name).
     * @param array|null $provider Provider data (must contain timezone). When provided,
     *                            the appointment date/time is formatted in this timezone.
     *
     * @return void
     */
    public function send_reminder(array $appointment, array $customer, array $service, ?array $provider = null): void
    {
        try {
            $rawPhone = $customer['phone_number'] ?? null;
            $customerId = $customer['id'] ?? null;

            $phone = $this->normalizePhoneForWhatsapp($rawPhone);

            if ($phone === null) {
                $this->log(
                    'Reminder skipped: invalid phone for customer #' . ($customerId ?? 'N/A') . ': ' . ($rawPhone ?: '(empty)')
                );
                return;
            }

            if (empty($this->reminderTemplate)) {
                $this->log('Reminder skipped: FLAXXA_REMINDER_TEMPLATE not configured.');
                return;
            }

            $components = [
                $this->buildHeaderComponent($customer),
                $this->buildReminderBody($appointment, $service, $provider),
            ];

            if ($this->logOnly) {
                $this->log(
                    'LOG_ONLY reminder would send to ' . $phone . ' using template "' . $this->reminderTemplate . '": ' . json_encode($components)
                );
                return;
            }

            $this->send($phone, $this->reminderTemplate, $components);
        } catch (Throwable $e) {
            $this->log(
                'Reminder failed for customer #' . ($customer['id'] ?? 'N/A') . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * Send a marketing WhatsApp message to a customer.
     *
     * Unlike send_confirmation/send_reminder, this method returns a result
     * array instead of swallowing errors, so the caller (marketing page)
     * can display per-recipient success/failure.
     *
     * Variables of the approved marketing template:
     *   {{header_1}} = customer full name
     *   {{body_1}} = procedure name
     *   {{body_2}} = discount percentage
     *   {{body_3}} = offer validity date
     *
     * @param array $customer Customer data (must contain phone_number and id).
     * @param string $procedure Procedure/service name promoted in the offer.
     * @param string $discount Discount percentage (e.g. "20").
     * @param string $validUntil Offer validity date (free text, e.g. "31 Decembrie").
     *
     * @return array Result array: ['success' => bool, 'error' => string|null]
     */
    public function send_marketing(array $customer, string $procedure, string $discount, string $validUntil): array
    {
        try {
            $rawPhone = $customer['phone_number'] ?? null;
            $customerId = $customer['id'] ?? null;

            $phone = $this->normalizePhoneForWhatsapp($rawPhone);

            if ($phone === null) {
                $this->log(
                    'Marketing skipped: invalid phone for customer #' . ($customerId ?? 'N/A') . ': ' . ($rawPhone ?: '(empty)')
                );

                return ['success' => false, 'error' => 'invalid_phone'];
            }

            $template = $this->marketingTemplate ?: 'bodysculpt_marketing';

            $components = [
                $this->buildHeaderComponent($customer),
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $procedure],
                        ['type' => 'text', 'text' => $discount],
                        ['type' => 'text', 'text' => $validUntil],
                    ],
                ],
            ];

            if ($this->logOnly) {
                $this->log(
                    'LOG_ONLY marketing would send to ' . $phone . ' using template "' . $template . '": ' . json_encode($components)
                );

                return ['success' => true, 'error' => null, 'log_only' => true];
            }

            $this->send($phone, $template, $components);

            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            $this->log(
                'Marketing failed for customer #' . ($customer['id'] ?? 'N/A') . ': ' . $e->getMessage()
            );

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Normalize a Romanian phone number to E.164 with leading '+' for Flaxxa.
     *
     * @param string|null $phone Raw phone number.
     *
     * @return string|null Normalized E.164 number (e.g. +40722334455) or null if invalid.
     */
    private function normalizePhoneForWhatsapp(?string $phone): ?string
    {
        $normalized = normalize_romanian_phone($phone);

        return $normalized !== null ? '+' . $normalized : null;
    }

    /**
     * Build the header component with the customer's full name.
     *
     * Variable:
     *   {{header_1}} = full name
     *
     * @param array $customer Customer data.
     *
     * @return array
     */
    private function buildHeaderComponent(array $customer): array
    {
        $firstName = $customer['first_name'] ?? '';
        $lastName = $customer['last_name'] ?? '';
        $name = trim($firstName . ' ' . $lastName);

        if ($name === '') {
            $name = 'client';
        }

        return [
            'type' => 'header',
            'parameters' => [
                ['type' => 'text', 'text' => $name],
            ],
        ];
    }

    /**
     * Build the body component for the confirmation template.
     *
     * Variables:
     *   {{body_1}} = date ("20 Iunie")
     *   {{body_2}} = time ("HH:MM")
     *   {{body_3}} = procedure/service name
     *
     * @param array $appointment Appointment data.
     * @param array $service Service data.
     * @param array|null $provider Provider data (timezone source).
     *
     * @return array
     */
    private function buildConfirmationBody(array $appointment, array $service, ?array $provider = null): array
    {
        [$date, $time] = $this->formatDateTime($appointment, $provider);
        $serviceName = $service['name'] ?? '-';

        return [
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $date],
                ['type' => 'text', 'text' => $time],
                ['type' => 'text', 'text' => $serviceName],
            ],
        ];
    }

    /**
     * Build the body component for the reminder template.
     *
     * Variables:
     *   {{body_1}} = time ("HH:MM")
     *   {{body_2}} = procedure/service name
     *
     * @param array $appointment Appointment data.
     * @param array $service Service data.
     * @param array|null $provider Provider data (timezone source).
     *
     * @return array
     */
    private function buildReminderBody(array $appointment, array $service, ?array $provider = null): array
    {
        [, $time] = $this->formatDateTime($appointment, $provider);
        $serviceName = $service['name'] ?? '-';

        return [
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $time],
                ['type' => 'text', 'text' => $serviceName],
            ],
        ];
    }

    /**
     * Format the appointment date/time in the provider's timezone.
     *
     * This mirrors the behaviour of Email_messages.php so that WhatsApp/SMS
     * show the same date and time as the calendar and emails.
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
     * Perform the HTTP POST to Flaxxa WAPI.
     *
     * @param string $phone E.164 phone number with leading '+'.
     * @param string $templateName Approved template name.
     * @param array $components Template components (body parameters).
     *
     * @return void
     *
     * @throws Exception If the API returns an error.
     */
    private function send(string $phone, string $templateName, array $components): void
    {
        $payload = [
            'token' => $this->apiToken,
            'phone' => $phone,
            'template_name' => $templateName,
            'template_language' => $this->templateLanguage,
            'components' => $components,
        ];

        $ch = curl_init(self::API_URL);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false) {
            throw new Exception('cURL error: ' . $curlError);
        }

        $decoded = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300 || !empty($decoded['error'])) {
            $errorMessage = $decoded['message'] ?? $decoded['error'] ?? $response;
            throw new Exception('Flaxxa API error (HTTP ' . $httpCode . '): ' . $errorMessage);
        }

        $this->log('Message sent to ' . $phone . ' using template "' . $templateName . '"');
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
        log_message('debug', '[wa-flaxxa] ' . $message);
    }
}
