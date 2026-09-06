<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Make (make.com) → Meta Lead Ads webhook receiver (public endpoint,
 * shared-secret verified).
 * ---------------------------------------------------------------------------- */

/**
 * Webhooks_make controller.
 *
 * Receives Meta Lead Ads lead data forwarded by a Make (make.com) scenario and
 * stores it in the meta_leads table using the SAME save path as the direct
 * Meta leadgen webhook (Webhooks_meta), so a lead delivered via Make is
 * identical to one delivered directly by Meta.
 *
 * This controller is intentionally PUBLIC (no session guard) because Make's
 * servers call it directly. The security boundary is the shared secret header:
 *
 *   X-Bookings-Secret: <MAKE_WEBHOOK_SECRET>
 *
 * Route: POST webhooks/make (see application/config/routes.php).
 * The route is excluded from CSRF checks (csrf_exclude_uris) because Make's
 * POST carries no CSRF token.
 */
class Webhooks_make extends EA_Controller
{
    /**
     * Webhooks_make constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('meta_leads_model');
        $this->load->helper('phone');
    }

    /**
     * Receive a Meta Lead Ads lead forwarded by Make.
     *
     * POST webhooks/make
     */
    public function receive(): void
    {
        if (strtoupper((string) $this->input->method()) !== 'POST') {
            json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);

            return;
        }

        // Security boundary: shared-secret header check (timing-safe). The secret
        // can be set via the MAKE_WEBHOOK_SECRET env var or a Config constant.
        $secret = $_SERVER['HTTP_X_BOOKINGS_SECRET'] ?? '';
        $expected = (string) getenv('MAKE_WEBHOOK_SECRET');

        if ($expected === '' && defined('Config::MAKE_WEBHOOK_SECRET')) {
            $expected = (string) constant('Config::MAKE_WEBHOOK_SECRET');
        }

        if ($expected === '') {
            log_message('error', '[make-webhook] MAKE_WEBHOOK_SECRET is not configured; rejecting request.');

            json_response(['ok' => false, 'error' => 'unauthorized'], 401);

            return;
        }

        if ($secret === '' || !hash_equals($expected, $secret)) {
            log_message('error', '[make-webhook] Secret header missing or invalid; rejecting request.');

            json_response(['ok' => false, 'error' => 'unauthorized'], 401);

            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data)) {
            json_response(['ok' => false, 'error' => 'invalid_json'], 400);

            return;
        }

        // The lead ID is Meta's leadgen_id: the same idempotency key the direct
        // Meta webhook uses, so a lead delivered twice (or once via Meta and once
        // via Make) is stored only once.
        $lead_id = (string) ($data['lead_id'] ?? $data['leadgen_id'] ?? '');

        if ($lead_id === '') {
            json_response(['ok' => false, 'error' => 'missing_lead_id'], 400);

            return;
        }

        // Idempotency: reuse the Meta leadgen dedupe (unique key on leadgen_id).
        if ($this->meta_leads_model->find_by_leadgen_id($lead_id)) {
            json_response(['ok' => true, 'lead_id' => $lead_id, 'duplicate' => true]);

            return;
        }

        try {
            // Reuse the tested save() path (insert + received_at/create/update
            // timestamps) from the Meta lead flow.
            $this->meta_leads_model->save($this->map_lead($lead_id, $data));
        } catch (Throwable $e) {
            log_message('error', '[make-webhook] Failed to process lead ' . $lead_id . ': ' . $e->getMessage());
            error_log('[make-webhook] Failed to process lead ' . $lead_id . ': ' . $e->getMessage());

            json_response(['ok' => false, 'error' => 'processing_failed'], 500);

            return;
        }

        json_response(['ok' => true, 'lead_id' => $lead_id]);
    }

    /**
     * Map a flat Make payload into a meta_leads record.
     *
     * Mirrors the column shape produced by Webhooks_meta::map_lead(), reusing
     * the same full_name split fallback and phone normalization. Only the input
     * source differs (flat JSON instead of Meta's nested field_data array).
     *
     * @param string $lead_id Meta leadgen ID (idempotency key).
     * @param array  $data    Decoded JSON body.
     *
     * @return array
     */
    private function map_lead(string $lead_id, array $data): array
    {
        $first_name = trim((string) ($data['first_name'] ?? ''));
        $last_name = trim((string) ($data['last_name'] ?? ''));
        $full_name = trim((string) ($data['full_name'] ?? ''));

        // Fall back to splitting a single "full name" field.
        if (($first_name === '' || $last_name === '') && $full_name !== '') {
            $parts = preg_split('/\s+/', $full_name, 2);

            $first_name = $first_name !== '' ? $first_name : ($parts[0] ?? '');
            $last_name = $last_name !== '' ? $last_name : ($parts[1] ?? '');
        }

        $raw_phone = (string) ($data['phone'] ?? $data['phone_number'] ?? '');

        $normalized_phone = normalize_international_phone($raw_phone);

        $form_fields = $data['form_fields'] ?? null;

        return [
            'leadgen_id' => $lead_id,
            'page_id' => (string) ($data['page_id'] ?? ''),
            'form_id' => (string) ($data['form_id'] ?? ''),
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => (string) ($data['email'] ?? ''),
            'phone_number' => $normalized_phone !== null ? '+' . $normalized_phone : '',
            'form_fields' => $form_fields === null ? null : (is_string($form_fields) ? $form_fields : json_encode($form_fields)),
            'status' => 'new',
        ];
    }
}
