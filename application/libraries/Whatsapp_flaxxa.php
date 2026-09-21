<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Flaxxa WAPI WhatsApp sender library.
 * ---------------------------------------------------------------------------- */

/**
 * Class Whatsapp_flaxxa
 *
 * Sends WhatsApp template messages through one of two providers, selected by the
 * WA_PROVIDER environment variable:
 *
 *   flaxxa (default) - the Flaxxa WAPI REST API
 *   meta             - the WhatsApp Cloud API directly, POST /{phone_number_id}/messages
 *
 * The class name is historical: Flaxxa is still the default, and the only provider
 * until WA_PROVIDER says otherwise. Both providers are handed the same approved
 * Meta template names and the same component list, because Flaxxa's WAPI is a
 * wrapper around the same Graph API — which is what makes the switch a
 * configuration change rather than a rewrite.
 */
class Whatsapp_flaxxa
{
    /**
     * @var string Flaxxa WAPI endpoint for sending template messages.
     */
    private const FLAXXA_API_URL = 'https://wapi.flaxxa.com/api/v1/sendtemplatemessage';

    /**
     * @var string Graph API host used by the meta provider.
     */
    private const GRAPH_API_BASE_URL = 'https://graph.facebook.com';

    /**
     * @var string Send via Flaxxa WAPI (default).
     */
    private const PROVIDER_FLAXXA = 'flaxxa';

    /**
     * @var string Send via the WhatsApp Cloud API.
     */
    private const PROVIDER_META = 'meta';

    /**
     * @var CI_Controller|object CodeIgniter instance.
     */
    protected $CI;

    /**
     * @var string Active provider (self::PROVIDER_FLAXXA or self::PROVIDER_META).
     */
    protected string $provider = self::PROVIDER_FLAXXA;

    /**
     * @var string Graph API version, with its leading "v" (e.g. "v22.0").
     */
    protected string $graphVersion = 'v22.0';

    /**
     * @var string|null System User token, used only by the meta provider.
     */
    protected ?string $metaToken = null;

    /**
     * @var string|null The clinic's WhatsApp phone_number_id, used only by the meta provider.
     */
    protected ?string $metaPhoneNumberId = null;

    /**
     * @var string|null Flaxxa API token, unused once WA_PROVIDER=meta.
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
     * @var string|null Why nothing will be sent, when nothing will be.
     *
     * A silent log-only mode is the one failure that looks exactly like success
     * from the outside, so the reason travels with the flag.
     */
    protected ?string $logOnlyReason = null;

    /**
     * Whatsapp_flaxxa constructor.
     *
     * @param array|null $config Optional ['provider' => 'flaxxa'|'meta'] override. Used by
     *                           Console::wa_test_send to exercise the meta transport while
     *                           WA_PROVIDER still says flaxxa.
     */
    public function __construct(?array $config = null)
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

        $this->resolveProvider($config);

        // LOG_ONLY mode lets you test the integration without sending real WhatsApp messages.
        // Which credential decides that depends on the provider: the Flaxxa token is
        // irrelevant once sends travel over the Graph API, and vice versa.
        $reasons = [];

        $token = $this->provider === self::PROVIDER_META ? $this->metaToken : $this->apiToken;
        $tokenName = $this->provider === self::PROVIDER_META ? 'BODYSCULPT_WA_TOKEN' : 'FLAXXA_API_TOKEN';

        if (empty($token)) {
            $reasons[] = $tokenName . ' is not set';
        } elseif (strtoupper($token) === 'LOG_ONLY') {
            $reasons[] = $tokenName . ' is LOG_ONLY';
        }

        // The Graph API addresses the message with the phone_number_id in the URL
        // rather than with the token, so a missing one is exactly as fatal.
        if ($this->provider === self::PROVIDER_META && empty($this->metaPhoneNumberId)) {
            $reasons[] = 'META_WA_PHONE_NUMBER_ID is not set';
        }

        if ($reasons) {
            $this->logOnly = true;
            $this->logOnlyReason = implode('; ', $reasons);
        }

        $this->CI->load->helper('phone');
    }

    /**
     * Decide which provider this instance sends through.
     *
     * An explicit $config['provider'] wins over the environment — that is how
     * Console::wa_test_send exercises the meta transport while WA_PROVIDER still
     * says flaxxa, which is the entire point of testing before switching.
     *
     * An unrecognised WA_PROVIDER value falls back to flaxxa rather than failing:
     * Flaxxa still works, so a typo keeps messages flowing instead of silently
     * stopping them. The warning is what stops the fallback being silent.
     *
     * @param array|null $config Optional constructor config.
     *
     * @return void
     */
    private function resolveProvider(?array $config): void
    {
        $requested = $config['provider'] ?? $this->readEnvOrConfig('WA_PROVIDER');

        if ($requested === null || trim((string) $requested) === '') {
            return;
        }

        $requested = strtolower(trim((string) $requested));

        if ($requested === self::PROVIDER_META) {
            $this->provider = self::PROVIDER_META;

            $this->metaToken = $this->readEnvOrConfig('BODYSCULPT_WA_TOKEN');
            $this->metaPhoneNumberId = $this->readEnvOrConfig('META_WA_PHONE_NUMBER_ID');

            $version = $this->readEnvOrConfig('META_GRAPH_VERSION');
            if (!empty($version)) {
                // Accepts both "v22.0" and "22.0" — the rest of the app writes the
                // version with its "v", but an operator pasting from Meta's docs
                // usually copies it without.
                $this->graphVersion = 'v' . ltrim($version, 'vV');
            }

            return;
        }

        if ($requested !== self::PROVIDER_FLAXXA) {
            // 'error', not 'warning': this CodeIgniter's Log::$_levels has no
            // WARNING entry, so a 'warning' line is dropped without ever being
            // written. Reported at error level because it means the operator
            // asked for a provider and is silently getting a different one.
            log_message(
                'error',
                '[wa-flaxxa] Unrecognised WA_PROVIDER "' . $requested . '" — sending via flaxxa. '
                    . 'Set WA_PROVIDER to "flaxxa" or "meta".'
            );
        }
    }

    /**
     * The provider this instance actually sends through ('flaxxa' or 'meta').
     *
     * @return string
     */
    public function get_provider(): string
    {
        return $this->provider;
    }

    /**
     * Why nothing will be sent, or null when sends are live.
     *
     * @return string|null
     */
    public function get_log_only_reason(): ?string
    {
        return $this->logOnlyReason;
    }

    /**
     * The template names the appointment senders will use.
     *
     * Read from here rather than from the environment by the caller, so a test
     * report names the same template the sender actually resolves — including
     * the Config fallback in readEnvOrConfig() and the built-in defaults.
     *
     * @return array ['confirmation' => string|null, 'reminder' => string|null]
     */
    public function get_configured_templates(): array
    {
        return [
            'confirmation' => $this->confirmationTemplate,
            'reminder' => $this->reminderTemplate,
        ];
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
     * it never propagates errors to the caller. The outcome is returned as well
     * as logged, so a caller that cares can act on it — routine appointment
     * traffic ignores the return value.
     *
     * @param array $appointment Appointment data (must contain start_datetime).
     * @param array $customer Customer data (must contain phone_number and id).
     * @param array $service Service data (must contain name).
     * @param array|null $provider Provider data (must contain timezone). When provided,
     *                            the appointment date/time is formatted in this timezone.
     *
     * @return array Result array: ['success' => bool, 'error' => string|null]
     */
    public function send_confirmation(array $appointment, array $customer, array $service, ?array $provider = null): array
    {
        try {
            $rawPhone = $customer['phone_number'] ?? null;
            $customerId = $customer['id'] ?? null;

            $phone = $this->normalizePhoneForWhatsapp($rawPhone);

            if ($phone === null) {
                $this->log(
                    'Confirmation skipped: invalid phone for customer #' . ($customerId ?? 'N/A') . ': ' . ($rawPhone ?: '(empty)')
                );
                return ['success' => false, 'error' => 'invalid_phone'];
            }

            if (empty($this->confirmationTemplate)) {
                $this->log('Confirmation skipped: FLAXXA_CONFIRMATION_TEMPLATE not configured.');
                return ['success' => false, 'error' => 'template_not_configured'];
            }

            $components = [
                $this->buildHeaderComponent($customer),
                $this->buildConfirmationBody($appointment, $service, $provider),
            ];

            if ($this->logOnly) {
                $this->log(
                    'LOG_ONLY confirmation would send to ' . $phone . ' using template "' . $this->confirmationTemplate . '"'
                        . ' (' . $this->logOnlyReason . '): ' . json_encode($components)
                );
                return ['success' => true, 'error' => null, 'log_only' => true];
            }

            $this->send($phone, $this->confirmationTemplate, $components);

            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            $this->log(
                'Confirmation failed for customer #' . ($customer['id'] ?? 'N/A') . ': ' . $e->getMessage()
            );

            return ['success' => false, 'error' => $e->getMessage()];
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
     * @return array Result array: ['success' => bool, 'error' => string|null]
     */
    public function send_reminder(array $appointment, array $customer, array $service, ?array $provider = null): array
    {
        try {
            $rawPhone = $customer['phone_number'] ?? null;
            $customerId = $customer['id'] ?? null;

            $phone = $this->normalizePhoneForWhatsapp($rawPhone);

            if ($phone === null) {
                $this->log(
                    'Reminder skipped: invalid phone for customer #' . ($customerId ?? 'N/A') . ': ' . ($rawPhone ?: '(empty)')
                );
                return ['success' => false, 'error' => 'invalid_phone'];
            }

            if (empty($this->reminderTemplate)) {
                $this->log('Reminder skipped: FLAXXA_REMINDER_TEMPLATE not configured.');
                return ['success' => false, 'error' => 'template_not_configured'];
            }

            $components = [
                $this->buildHeaderComponent($customer),
                $this->buildReminderBody($appointment, $service, $provider),
            ];

            if ($this->logOnly) {
                $this->log(
                    'LOG_ONLY reminder would send to ' . $phone . ' using template "' . $this->reminderTemplate . '"'
                        . ' (' . $this->logOnlyReason . '): ' . json_encode($components)
                );
                return ['success' => true, 'error' => null, 'log_only' => true];
            }

            $this->send($phone, $this->reminderTemplate, $components);

            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            $this->log(
                'Reminder failed for customer #' . ($customer['id'] ?? 'N/A') . ': ' . $e->getMessage()
            );

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a marketing WhatsApp message to a customer.
     *
     * Returns a result array rather than swallowing the error, so the caller
     * (marketing page) can display per-recipient success/failure. All six
     * senders here return that same shape; send_confirmation and send_reminder
     * simply have no caller reading it.
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
     * Send a Stripe deposit payment link to the customer.
     *
     * Uses the template named by STRIPE_PAYMENT_TEMPLATE_NAME (placeholder:
     * the approved test template; production: 'link_plata'). The component
     * layout matches the future 'link_plata' template exactly, so switching
     * templates later only requires a config change:
     *   {{header_1}} = customer full name
     *   {{body_1}} = procedure/service name
     *   {{body_2}} = appointment date and time ("20 Iunie 14:30")
     *   {{body_3}} = Stripe payment URL
     *
     * Like send_marketing, returns a result array so the caller (payments
     * endpoint) can report the send status to the staff member.
     *
     * @param array $appointment Appointment data (must contain start_datetime).
     * @param array $customer Customer data (must contain phone_number and id).
     * @param array $service Service data (must contain name).
     * @param array|null $provider Provider data (timezone source).
     * @param string $paymentUrl Stripe Checkout Session URL.
     *
     * @return array Result array: ['success' => bool, 'error' => string|null]
     */
    public function send_payment_link(array $appointment, array $customer, array $service, ?array $provider, string $paymentUrl): array
    {
        try {
            $rawPhone = $customer['phone_number'] ?? null;
            $customerId = $customer['id'] ?? null;

            $phone = $this->normalizePhoneForWhatsapp($rawPhone);

            if ($phone === null) {
                $this->log(
                    'Payment link skipped: invalid phone for customer #' . ($customerId ?? 'N/A') . ': ' . ($rawPhone ?: '(empty)')
                );

                return ['success' => false, 'error' => 'invalid_phone'];
            }

            $template = $this->readEnvOrConfig('STRIPE_PAYMENT_TEMPLATE_NAME');

            if (empty($template)) {
                $this->log('Payment link skipped: STRIPE_PAYMENT_TEMPLATE_NAME not configured.');

                return ['success' => false, 'error' => 'template_not_configured'];
            }

            $components = [
                $this->buildHeaderComponent($customer),
                $this->buildPaymentLinkBody($appointment, $service, $provider, $paymentUrl),
            ];

            if ($this->logOnly) {
                $this->log(
                    'LOG_ONLY payment link would send to ' . $phone . ' using template "' . $template . '": ' . json_encode($components)
                );

                return ['success' => true, 'error' => null, 'log_only' => true];
            }

            $this->send($phone, $template, $components);

            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            $this->log(
                'Payment link failed for customer #' . ($customer['id'] ?? 'N/A') . ': ' . $e->getMessage()
            );

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the body component for the payment link template.
     *
     * Variables:
     *   {{body_1}} = procedure/service name
     *   {{body_2}} = appointment date and time ("20 Iunie 14:30")
     *   {{body_3}} = Stripe payment URL
     *
     * @param array $appointment Appointment data.
     * @param array $service Service data.
     * @param array|null $provider Provider data (timezone source).
     * @param string $paymentUrl Stripe Checkout Session URL.
     *
     * @return array
     */
    private function buildPaymentLinkBody(array $appointment, array $service, ?array $provider, string $paymentUrl): array
    {
        [$date, $time] = $this->formatDateTime($appointment, $provider);
        $serviceName = $service['name'] ?? '-';

        return [
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $serviceName],
                ['type' => 'text', 'text' => trim($date . ' ' . $time)],
                ['type' => 'text', 'text' => $paymentUrl],
            ],
        ];
    }

    /**
     * Notify the CUSTOMER that the appointment was auto-cancelled because the
     * deposit was not paid within 24 hours of sending the payment link.
     *
     * Uses the template named by CLIENT_CANCEL_TEMPLATE_NAME ('avans_neplatit').
     * Component layout (THREE variables total):
     *   {{header_1}} = customer full name
     *   {{body_1}} = procedure/service name
     *   {{body_2}} = appointment date and time ("20 Iunie 14:30")
     *
     * The "cancelled due to unpaid deposit" semantics live in the template text.
     *
     * @param array $appointment Appointment data (must contain start_datetime).
     * @param array $customer Customer data (must contain phone_number and id).
     * @param array $service Service data (must contain name).
     * @param array|null $provider Provider data (timezone source).
     *
     * @return array Result array: ['success' => bool, 'error' => string|null]
     */
    public function send_appointment_cancelled_unpaid(array $appointment, array $customer, array $service, ?array $provider): array
    {
        try {
            $rawPhone = $customer['phone_number'] ?? null;
            $customerId = $customer['id'] ?? null;

            $phone = $this->normalizePhoneForWhatsapp($rawPhone);

            if ($phone === null) {
                $this->log(
                    'Cancel-unpaid notification skipped: invalid phone for customer #' . ($customerId ?? 'N/A') . ': ' . ($rawPhone ?: '(empty)')
                );

                return ['success' => false, 'error' => 'invalid_phone'];
            }

            $template = $this->readEnvOrConfig('CLIENT_CANCEL_TEMPLATE_NAME');

            if (empty($template)) {
                $this->log('Cancel-unpaid notification skipped: CLIENT_CANCEL_TEMPLATE_NAME not configured.');

                return ['success' => false, 'error' => 'template_not_configured'];
            }

            [$date, $time] = $this->formatDateTime($appointment, $provider);

            $components = [
                $this->buildHeaderComponent($customer),
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $service['name'] ?? '-'],
                        ['type' => 'text', 'text' => trim($date . ' ' . $time)],
                    ],
                ],
            ];

            if ($this->logOnly) {
                $this->log(
                    'LOG_ONLY cancel-unpaid notification would send to ' . $phone . ' using template "' . $template . '": ' . json_encode($components)
                );

                return ['success' => true, 'error' => null, 'log_only' => true];
            }

            $this->send($phone, $template, $components);

            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            $this->log('Cancel-unpaid notification failed for appointment #' . ($appointment['id'] ?? 'N/A') . ': ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send an issued invoice's PDF to the billing client on WhatsApp.
     *
     * Uses the template named by FLAXXA_INVOICE_TEMPLATE (default 'factura_pdf').
     * The template must have a DOCUMENT header (no variables) and a body with
     * THREE text variables:
     *   {{1}} = client name
     *   {{2}} = invoice number ("AM-0001"; drafts have no number)
     *   {{3}} = invoice total with currency
     *
     * The PDF is attached dynamically as a document header parameter pointing
     * to a public short link (/inv/<slug>) that Meta fetches at send time.
     *
     * @param array $client Billing client data (must contain name and phone).
     * @param array $invoice Invoice data (series, number, total, is_draft).
     * @param string $pdfUrl Publicly reachable URL of the invoice PDF.
     *
     * @return array Result array: ['success' => bool, 'error' => string|null]
     */
    public function send_invoice_pdf(array $client, array $invoice, string $pdfUrl): array
    {
        try {
            $phone = $this->normalizePhoneForWhatsapp($client['phone'] ?? null);

            if ($phone === null) {
                $this->log(
                    'Invoice PDF skipped: invalid phone for billing client #' . ($client['id'] ?? 'N/A') . ': ' . ($client['phone'] ?: '(empty)')
                );

                return ['success' => false, 'error' => 'invalid_phone'];
            }

            $template = $this->readEnvOrConfig('FLAXXA_INVOICE_TEMPLATE') ?: 'factura_pdf';

            $invoiceNumber = !empty($invoice['number'])
                ? ($invoice['series'] ?? '') . '-' . $invoice['number']
                : ($invoice['series'] ?? '');

            $fileName = 'factura-' . ($invoice['series'] ?? '') . ($invoice['number'] ?? '') . '.pdf';

            $components = [
                [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'document',
                            'document' => [
                                'link' => $pdfUrl,
                                'filename' => $fileName,
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => trim((string) ($client['name'] ?? '')) ?: 'client'],
                        ['type' => 'text', 'text' => $invoiceNumber],
                        ['type' => 'text', 'text' => number_format((float) ($invoice['total'] ?? 0), 2) . ' Lei'],
                    ],
                ],
            ];

            if ($this->logOnly) {
                $this->log(
                    'LOG_ONLY invoice PDF would send to ' . $phone . ' using template "' . $template . '": ' . json_encode($components)
                );

                return ['success' => true, 'error' => null, 'log_only' => true];
            }

            $this->send($phone, $template, $components);

            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            $this->log('Invoice PDF failed for billing client #' . ($client['id'] ?? 'N/A') . ': ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Normalize an international phone number to E.164 with leading '+' for Flaxxa.
     *
     * @param string|null $phone Raw phone number.
     *
     * @return string|null Normalized E.164 number (e.g. +393123456789) or null if invalid.
     */
    private function normalizePhoneForWhatsapp(?string $phone): ?string
    {
        $normalized = normalize_international_phone($phone);

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
     * Perform the HTTP POST via the configured provider.
     *
     * Every one of the six senders ends up here, which is why the provider switch
     * lives at this point and nowhere else: no caller knows which provider is
     * active, and none of them changed when it was added.
     *
     * @param string $phone E.164 phone number with leading '+'.
     * @param string $templateName Approved template name.
     * @param array $components Template components (header and body).
     *
     * @return void
     *
     * @throws Exception If the API returns an error.
     */
    private function send(string $phone, string $templateName, array $components): void
    {
        if ($this->provider === self::PROVIDER_META) {
            $this->sendViaMeta($phone, $templateName, $components);

            return;
        }

        $this->sendViaFlaxxa($phone, $templateName, $components);
    }

    /**
     * Perform the HTTP POST to the WhatsApp Cloud API.
     *
     * The body is the same message Flaxxa accepts, minus Flaxxa's own wrapper
     * fields (`token`, `phone`, `template_name`): Meta takes the token in the
     * Authorization header, the recipient as `to`, and the template name and
     * language inside a `template` object. The `components` array is passed
     * through untouched — it is already Meta's own format, with the header
     * element and the body element separate — which is why nothing above this
     * method changed when the provider did.
     *
     * @param string $phone E.164 phone number with leading '+'.
     * @param string $templateName Approved template name.
     * @param array $components Template components (header and body).
     *
     * @return void
     *
     * @throws Exception If the API returns an error.
     */
    private function sendViaMeta(string $phone, string $templateName, array $components): void
    {
        $url = self::GRAPH_API_BASE_URL . '/' . $this->graphVersion . '/' . $this->metaPhoneNumberId . '/messages';

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            // Meta wants the number as digits with its country code, and no '+'.
            'to' => ltrim($phone, '+'),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $this->templateLanguage],
                'components' => $components,
            ],
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->metaToken,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false) {
            throw new Exception('cURL error: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        $error = is_array($decoded) ? ($decoded['error'] ?? null) : null;

        if ($httpCode < 200 || $httpCode >= 300 || $error) {
            // Meta reports failures in an `error` object. Its `code` is kept because
            // it is the whole diagnosis and is what Meta's support asks for:
            // 190 = bad or expired token, 132001 = template does not exist in that
            // language, 131047 = outside the 24-hour customer service window.
            $errorMessage = is_array($error)
                ? ($error['message'] ?? 'unknown error') . ' (code ' . ($error['code'] ?? 'n/a') . ')'
                : ($error ?: $response);

            throw new Exception('Meta Graph API error (HTTP ' . $httpCode . '): ' . $errorMessage);
        }

        $this->log('Message sent to ' . $phone . ' using template "' . $templateName . '" via meta');
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
    private function sendViaFlaxxa(string $phone, string $templateName, array $components): void
    {
        $payload = [
            'token' => $this->apiToken,
            'phone' => $phone,
            'template_name' => $templateName,
            'template_language' => $this->templateLanguage,
            'components' => $components,
        ];

        $ch = curl_init(self::FLAXXA_API_URL);

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

        // Flaxxa signals failures either via an "error" key or via
        // {"status": "error", "message": "..."} — both must be detected,
        // otherwise rejected sends are falsely reported as successful.
        if (
            $httpCode < 200
            || $httpCode >= 300
            || !empty($decoded['error'])
            || ($decoded['status'] ?? '') === 'error'
        ) {
            $errorMessage = $decoded['message'] ?? $decoded['error'] ?? $response;
            throw new Exception('Flaxxa API error (HTTP ' . $httpCode . '): ' . $errorMessage);
        }

        $this->log('Message sent to ' . $phone . ' using template "' . $templateName . '" via flaxxa');
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
