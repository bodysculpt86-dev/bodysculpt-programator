<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Stripe deposit payments controller (staff-only).
 * ---------------------------------------------------------------------------- */

/**
 * Class Payments
 *
 * Handles Stripe Checkout Session creation for appointment deposits.
 *
 * All endpoints require an authenticated staff session with the appointments
 * privilege. They are never publicly reachable.
 */
class Payments extends EA_Controller
{
    /**
     * Payments constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('customers_model');
        $this->load->model('services_model');
        $this->load->model('providers_model');
        $this->load->model('payment_links_model');

        $this->load->library('whatsapp_flaxxa');
    }

    /**
     * Create a Stripe Checkout Session for the deposit of an appointment and
     * send the payment link to the customer via WhatsApp.
     *
     * POST payments/create_checkout_session
     *
     * Request parameters:
     *  - appointment_id (integer, required)
     *
     * JSON response:
     *  - success (bool)
     *  - checkout_url (string) Stripe-hosted payment page URL
     *  - stripe_session_id (string)
     *  - whatsapp (array) ['success' => bool, 'error' => string|null]
     */
    public function create_checkout_session(): void
    {
        try {
            method('post');

            check('appointment_id', 'integer');

            if (cannot('edit', PRIV_APPOINTMENTS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            $appointment_id = (int) request('appointment_id');

            $appointment = $this->appointments_model->find($appointment_id);

            if (empty($appointment) || !empty($appointment['is_unavailability'])) {
                throw new InvalidArgumentException('Appointment not found.');
            }

            if (($appointment['deposit_status'] ?? 'none') === 'paid') {
                throw new RuntimeException('The deposit for this appointment is already paid.');
            }

            $secret_key = $this->readEnvOrConfig('STRIPE_SECRET_KEY');

            if (empty($secret_key)) {
                throw new RuntimeException('Stripe is not configured (STRIPE_SECRET_KEY is missing).');
            }

            $deposit_amount = $this->readEnvOrConfig('STRIPE_DEPOSIT_AMOUNT');

            // Convert RON to bani (smallest currency unit) for Stripe.
            $unit_amount = (int) round(((float) $deposit_amount) * 100);

            if ($unit_amount <= 0) {
                throw new RuntimeException('Invalid deposit amount (STRIPE_DEPOSIT_AMOUNT).');
            }

            $customer = $this->customers_model->find($appointment['id_users_customer']);
            $service = $this->services_model->find($appointment['id_services']);
            $provider = $this->providers_model->find($appointment['id_users_provider']);

            $stripe = new \Stripe\StripeClient($secret_key);

            $session_params = [
                'mode' => 'payment',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => 'ron',
                        'unit_amount' => $unit_amount,
                        'product_data' => [
                            'name' => 'Avans programare BodySculpt',
                            'description' => ($service['name'] ?? '-') . ' · ' . ($appointment['start_datetime'] ?? ''),
                        ],
                    ],
                ]],
                // CRITICAL: the webhook reads appointment_id from this metadata
                // in order to mark the appointment deposit as paid.
                'metadata' => [
                    'appointment_id' => (string) $appointment_id,
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'appointment_id' => (string) $appointment_id,
                    ],
                ],
                'success_url' => site_url('booking/deposit_success'),
                'cancel_url' => site_url('booking/deposit_cancelled'),
            ];

            // Prefill the customer email only when it is a valid address;
            // otherwise Stripe rejects the request with "Invalid email address"
            // (many customers only have a phone number on file).
            $customer_email = trim((string) ($customer['email'] ?? ''));

            if (filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
                $session_params['customer_email'] = $customer_email;
            }

            $session = $stripe->checkout->sessions->create($session_params);

            // Persist the backend-only deposit fields directly (these are NOT
            // part of the calendar $allowed_appointment_fields whitelist).
            $appointment['stripe_session_id'] = $session->id;
            $appointment['deposit_amount'] = $deposit_amount;
            $appointment['deposit_status'] = 'unpaid';
            $appointment['payment_link_sent_at'] = date('Y-m-d H:i:s');

            $this->appointments_model->save($appointment);

            // Create a short internal link that redirects to the long Stripe
            // Checkout URL. ONLY the WhatsApp template variable uses the short
            // URL; the Stripe session, the webhook and the staff-facing
            // checkout_url keep the original Stripe URL.
            $slug = $this->payment_links_model->create($session->url, $appointment_id);

            $short_link_base = $this->readEnvOrConfig('SHORT_LINK_BASE_URL');

            if (!empty($short_link_base)) {
                // Dedicated short-link host (e.g. https://pay.bodysculpt.ro):
                // the slug lives at the root — https://pay.bodysculpt.ro/abc123XY
                // — and Apache rewrites it to /index.php/pay/<slug> on that
                // host only (see docker-entrypoint-railway.sh).
                $short_url = rtrim($short_link_base, '/') . '/' . $slug;
            } else {
                // Default: app domain without the index.php segment + /pay/.
                $short_url = preg_replace('#/index\.php/?$#', '', site_url()) . '/pay/' . $slug;
            }

            // Send the payment link via WhatsApp. A send failure does not roll
            // back the session: the link exists and is reported to the staff.
            $whatsapp_result = $this->whatsapp_flaxxa->send_payment_link(
                $appointment,
                $customer,
                $service,
                $provider,
                $short_url,
            );

            log_message(
                'debug',
                '[payments] Checkout session ' . $session->id . ' created for appointment #' . $appointment_id
                    . ' (WhatsApp: ' . ($whatsapp_result['success'] ? 'sent' : 'failed - ' . ($whatsapp_result['error'] ?? 'unknown')) . ')',
            );

            json_response([
                'success' => true,
                'checkout_url' => $session->url,
                'short_url' => $short_url,
                'stripe_session_id' => $session->id,
                'whatsapp' => $whatsapp_result,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
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
}
