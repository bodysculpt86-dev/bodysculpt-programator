<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Stripe webhook receiver (public endpoint, signature-verified).
 * ---------------------------------------------------------------------------- */

/**
 * Class Webhooks_stripe
 *
 * Receives Stripe webhook events and marks appointment deposits as paid.
 *
 * This controller is intentionally PUBLIC (no session guard) because Stripe
 * servers call it directly. The security boundary is the Stripe webhook
 * signature verification — unverified requests are always rejected.
 *
 * Route: POST webhooks/stripe (see application/config/routes.php).
 * The route is excluded from CSRF checks (csrf_exclude_uris) because Stripe's
 * POST does not carry a CSRF token.
 */
class Webhooks_stripe extends EA_Controller
{
    /**
     * Webhooks_stripe constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
    }

    /**
     * Receive a Stripe webhook event.
     *
     * POST webhooks/stripe
     */
    public function receive(): void
    {
        method('post');

        // Read the raw body FIRST: signature verification requires the exact,
        // unmodified payload as sent by Stripe.
        $payload = file_get_contents('php://input');

        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        $webhook_secret = $this->readEnvOrConfig('STRIPE_WEBHOOK_SECRET');

        if (empty($webhook_secret)) {
            log_message('error', '[stripe-webhook] STRIPE_WEBHOOK_SECRET is not configured; rejecting event.');

            $this->output->set_status_header(400);
            json_response(['received' => false, 'error' => 'webhook_not_configured']);

            return;
        }

        // Security boundary: never trust an unverified webhook payload.
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $webhook_secret);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            log_message('error', '[stripe-webhook] Signature verification failed: ' . $e->getMessage());

            $this->output->set_status_header(400);
            json_response(['received' => false, 'error' => 'invalid_signature']);

            return;
        } catch (Throwable $e) {
            log_message('error', '[stripe-webhook] Invalid payload: ' . $e->getMessage());

            $this->output->set_status_header(400);
            json_response(['received' => false, 'error' => 'invalid_payload']);

            return;
        }

        $handled_types = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];

        if (!in_array($event->type, $handled_types, true)) {
            // Acknowledge with 200 so Stripe does not retry unhandled types.
            log_message('debug', '[stripe-webhook] Ignored event type "' . $event->type . '" (event ' . $event->id . ').');

            json_response(['received' => true, 'ignored' => $event->type]);

            return;
        }

        /** @var \Stripe\Checkout\Session $session */
        $session = $event->data->object;

        $appointment_id = isset($session->metadata->appointment_id) ? (int) $session->metadata->appointment_id : null;

        if (empty($appointment_id)) {
            log_message('error', '[stripe-webhook] Event ' . $event->id . ' (' . $event->type . ') has no appointment_id in session metadata.');

            json_response(['received' => true, 'error' => 'missing_appointment_id']);

            return;
        }

        // For delayed payment methods the session completes before the money
        // arrives; wait for checkout.session.async_payment_succeeded instead.
        if (($session->payment_status ?? '') !== 'paid') {
            log_message(
                'debug',
                '[stripe-webhook] Session ' . $session->id . ' for appointment #' . $appointment_id
                    . ' not paid yet (payment_status=' . ($session->payment_status ?? 'n/a') . '); waiting.',
            );

            json_response(['received' => true, 'waiting_for_payment' => true]);

            return;
        }

        try {
            $appointment = $this->appointments_model->find($appointment_id);

            if (empty($appointment)) {
                log_message('error', '[stripe-webhook] Appointment #' . $appointment_id . ' not found (event ' . $event->id . ').');

                json_response(['received' => true, 'error' => 'appointment_not_found']);

                return;
            }

            // Idempotency: Stripe may deliver the same event more than once.
            if (($appointment['deposit_status'] ?? 'none') === 'paid') {
                log_message('debug', '[stripe-webhook] Appointment #' . $appointment_id . ' already marked as paid; skipping (event ' . $event->id . ').');

                json_response(['received' => true, 'already_paid' => true]);

                return;
            }

            // Safety cross-check: the paid session must be the one we created
            // and stored for this appointment.
            if (($appointment['stripe_session_id'] ?? null) !== $session->id) {
                // Acknowledged with 200 (retrying would never fix a mismatch),
                // but logged at error level for manual review — e.g. a customer
                // paying an older, replaced payment link.
                log_message(
                    'error',
                    '[stripe-webhook] Session mismatch for appointment #' . $appointment_id . ': event session '
                        . $session->id . ' != stored ' . ($appointment['stripe_session_id'] ?? '(none)') . ' (event ' . $event->id . ').',
                );

                json_response(['received' => true, 'error' => 'session_mismatch']);

                return;
            }

            // Backend-only fields (not part of the calendar whitelist).
            $appointment['deposit_status'] = 'paid';
            $appointment['deposit_paid_at'] = date('Y-m-d H:i:s');

            $this->appointments_model->save($appointment);

            log_message(
                'debug',
                '[stripe-webhook] Deposit marked as PAID for appointment #' . $appointment_id
                    . ' (session ' . $session->id . ', event ' . $event->id . ').',
            );

            json_response(['received' => true, 'appointment_id' => $appointment_id, 'deposit_status' => 'paid']);
        } catch (Throwable $e) {
            // Non-2xx tells Stripe to retry the delivery later.
            log_message('error', '[stripe-webhook] Processing failed for appointment #' . $appointment_id . ': ' . $e->getMessage());

            $this->output->set_status_header(500);
            json_response(['received' => false, 'error' => 'processing_failed']);
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
