<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Autocalls (AI caller) → BookingS call-result webhook receiver (public
 * endpoint, shared-secret verified).
 * ---------------------------------------------------------------------------- */

/**
 * Webhooks_autocalls controller.
 *
 * Receives the outcome of an AI phone call for a Meta lead (Autocalls calls
 * every lead that comes in through Make before a receptionist ever sees it)
 * and stores it on the matching meta_leads row for display only — it never
 * touches call_status, assigned_to or any part of the receptionist call
 * queue.
 *
 * This controller is intentionally PUBLIC (no session guard) because
 * Autocalls' servers call it directly. The security boundary is the same
 * shared secret header the Make webhook uses:
 *
 *   X-Bookings-Secret: <MAKE_WEBHOOK_SECRET>
 *
 * Route: POST webhooks/autocalls (see application/config/routes.php).
 * The route is excluded from CSRF checks (csrf_exclude_uris) because
 * Autocalls' POST carries no CSRF token.
 */
class Webhooks_autocalls extends EA_Controller
{
    /**
     * Classifications Autocalls is allowed to report.
     */
    private const ALLOWED_CLASSIFICATIONS = ['HOT', 'WARM', 'COLD', 'CALLBACK', 'NO_ANSWER', 'FAILED'];

    /**
     * Webhooks_autocalls constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('meta_leads_model');
        $this->load->helper('phone');
    }

    /**
     * Receive an AI call result for a Meta lead.
     *
     * POST webhooks/autocalls
     */
    public function receive(): void
    {
        if (strtoupper((string) $this->input->method()) !== 'POST') {
            json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);

            return;
        }

        // Security boundary: same shared-secret header check (timing-safe) as
        // Webhooks_make. The secret can be set via the MAKE_WEBHOOK_SECRET env
        // var or a Config constant.
        $secret = $_SERVER['HTTP_X_BOOKINGS_SECRET'] ?? '';
        $expected = (string) getenv('MAKE_WEBHOOK_SECRET');

        if ($expected === '' && defined('Config::MAKE_WEBHOOK_SECRET')) {
            $expected = (string) constant('Config::MAKE_WEBHOOK_SECRET');
        }

        if ($expected === '') {
            log_message('error', '[autocalls-webhook] MAKE_WEBHOOK_SECRET is not configured; rejecting request.');

            json_response(['ok' => false, 'error' => 'unauthorized'], 401);

            return;
        }

        if ($secret === '' || !hash_equals($expected, $secret)) {
            log_message('error', '[autocalls-webhook] Secret header missing or invalid; rejecting request.');

            json_response(['ok' => false, 'error' => 'unauthorized'], 401);

            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data)) {
            json_response(['ok' => false, 'error' => 'invalid_json'], 400);

            return;
        }

        // Autocalls' casing isn't guaranteed ('hot', ' Hot', 'HOT' must all match).
        $classification = strtoupper(trim((string) ($data['classification'] ?? '')));
        $skip_save = false;

        if ($classification === '') {
            // No classification: derive one from call_status (Autocalls' own
            // lead.status, forwarded under this name) instead of rejecting the
            // call outright — a completed-but-unclassified call and a call that
            // never connected are both real, expected outcomes, not malformed
            // requests.
            $call_status = strtolower(trim((string) ($data['call_status'] ?? '')));

            if ($call_status === 'completed') {
                // Call connected, AI produced no classification: nothing to
                // store, but still a valid, expected delivery.
                $skip_save = true;
            } elseif ($call_status === 'no-answer') {
                $classification = 'NO_ANSWER';
            } elseif ($call_status === 'failed') {
                $classification = 'FAILED';
            } else {
                json_response(['ok' => false, 'error' => 'invalid_classification'], 400);

                return;
            }
        } elseif (!in_array($classification, self::ALLOWED_CLASSIFICATIONS, true)) {
            json_response(['ok' => false, 'error' => 'invalid_classification'], 400);

            return;
        }

        // Identification: leadgen_id only — an internal numeric ID and Meta's
        // leadgen_id are different kinds of value, and accepting either under
        // one ambiguous field risks matching the wrong lead. Phone is the only
        // fallback, used only when leadgen_id is absent or matches nothing.
        $leadgen_id = trim((string) ($data['leadgen_id'] ?? ''));

        $lead = null;

        if ($leadgen_id !== '') {
            $lead = $this->meta_leads_model->find_by_leadgen_id($leadgen_id);
        }

        if ($lead === null) {
            $raw_phone = (string) ($data['phone'] ?? '');
            $normalized_phone = $raw_phone !== '' ? normalize_international_phone($raw_phone) : null;

            if ($normalized_phone !== null) {
                $lead = $this->meta_leads_model->find_by_phone($normalized_phone);
            }
        }

        if ($lead === null) {
            json_response(['ok' => false, 'error' => 'lead_not_found'], 404);

            return;
        }

        if ($skip_save) {
            json_response(['ok' => true, 'lead_id' => (int) $lead['id'], 'skipped' => 'no_classification']);

            return;
        }

        $call_at = trim((string) ($data['call_at'] ?? ''));
        $call_at = $call_at !== '' && strtotime($call_at) !== false ? date('Y-m-d H:i:s', strtotime($call_at)) : date('Y-m-d H:i:s');

        $attempt_number = isset($data['attempt_number']) && is_numeric($data['attempt_number'])
            ? (int) $data['attempt_number']
            : null;

        try {
            $this->meta_leads_model->save_call_result($lead, [
                'classification' => $classification,
                'summary' => isset($data['call_summary']) ? substr(trim((string) $data['call_summary']), 0, 65535) : null,
                'desired_procedure' => isset($data['desired_procedure']) ? substr(trim((string) $data['desired_procedure']), 0, 256) : null,
                'attempt_number' => $attempt_number,
                'call_at' => $call_at,
                'recording_url' => isset($data['recording_url']) ? substr(trim((string) $data['recording_url']), 0, 512) : null,
            ]);
        } catch (Throwable $e) {
            log_message('error', '[autocalls-webhook] Failed to save call result for lead ' . $lead['id'] . ': ' . $e->getMessage());
            error_log('[autocalls-webhook] Failed to save call result for lead ' . $lead['id'] . ': ' . $e->getMessage());

            json_response(['ok' => false, 'error' => 'processing_failed'], 500);

            return;
        }

        json_response(['ok' => true, 'lead_id' => (int) $lead['id']]);
    }
}
