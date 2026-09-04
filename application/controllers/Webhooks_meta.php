<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Meta Lead Ads webhook receiver (public endpoint, signature-verified).
 * ---------------------------------------------------------------------------- */

/**
 * Webhooks_meta controller.
 *
 * Receives Meta leadgen notifications and stores new leads in the meta_leads
 * table. This controller is intentionally PUBLIC (no session guard) because
 * Meta's servers call it directly. The security boundary is:
 *
 *   - the verification handshake (verify token) on the GET request, and
 *   - the X-Hub-Signature-256 (HMAC-SHA256, app secret) on the POST request.
 *
 * Route: GET/POST webhooks/meta (see application/config/routes.php).
 * The route is excluded from CSRF checks because Meta's POST carries no CSRF token.
 */
class Webhooks_meta extends EA_Controller
{
    /**
     * Webhooks_meta constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('meta_leads_model');
        $this->load->library('meta_capi');
        $this->load->helper('phone');
    }

    /**
     * Handle Meta's webhook subscription verification handshake (GET).
     *
     * Echoes back hub.challenge only when the verify token matches.
     */
    public function verify(): void
    {
        $mode = $_GET['hub.mode'] ?? $_GET['hub_mode'] ?? '';
        $token = $_GET['hub.verify_token'] ?? $_GET['hub_verify_token'] ?? '';
        $challenge = $_GET['hub.challenge'] ?? $_GET['hub_challenge'] ?? '';

        $expected = $this->meta_conf('META_VERIFY_TOKEN');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, (string) $token)) {
            header('Content-Type: text/plain');

            echo $challenge;

            return;
        }

        show_404();
    }

    /**
     * Receive a Meta leadgen notification (POST).
     */
    public function receive(): void
    {
        method('post');

        // Read the raw body FIRST: signature verification requires the exact,
        // unmodified payload as sent by Meta.
        $payload = file_get_contents('php://input');

        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

        $app_secret = $this->meta_conf('META_APP_SECRET');

        if ($app_secret === '') {
            log_message('error', '[meta-webhook] META_APP_SECRET is not configured; rejecting notification.');

            json_response(['success' => false, 'error' => 'webhook_not_configured'], 400);

            return;
        }

        // Security boundary: never trust an unverified webhook payload.
        $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $app_secret);

        if (!hash_equals($expected_signature, $signature)) {
            log_message('error', '[meta-webhook] Signature verification failed.');

            json_response(['success' => false, 'error' => 'invalid_signature'], 400);

            return;
        }

        $data = json_decode($payload, true);

        // The leadgen object does NOT expose a "page_id" field — page_id (and
        // ad_id/form_id) arrive here in the webhook payload entry[].changes[].value.
        $value = $data['entry'][0]['changes'][0]['value'] ?? [];

        $leadgen_id = $value['leadgen_id'] ?? null;

        if (empty($leadgen_id)) {
            // Acknowledge so Meta does not retry a payload we cannot act on.
            json_response(['success' => true, 'ignored' => 'no_leadgen_id']);

            return;
        }

        // Idempotency: Meta may re-deliver the same notification.
        if ($this->meta_leads_model->find_by_leadgen_id($leadgen_id)) {
            json_response(['success' => true, 'duplicate' => true]);

            return;
        }

        try {
            $lead_data = $this->fetch_lead($leadgen_id);

            // page_id is sourced from the webhook payload, not the lead object.
            $lead_data['page_id'] = $value['page_id'] ?? '';

            $lead = $this->map_lead($leadgen_id, $lead_data);

            $this->meta_leads_model->save($lead);
        } catch (Throwable $e) {
            // Non-2xx tells Meta to retry the delivery later.
            log_message('error', '[meta-webhook] Failed to process lead ' . $leadgen_id . ': ' . $e->getMessage());

            // Also write to stderr (error_log) so the detail is visible in
            // Railway's streamed deployment logs — storage/logs is ephemeral.
            error_log('[meta-webhook] Failed to process lead ' . $leadgen_id . ': ' . $e->getMessage());
            error_log('[meta-webhook] Trace: ' . $e->getTraceAsString());

            json_response(['success' => false, 'error' => 'processing_failed'], 500);

            return;
        }

        // Send the initial CRM stage so Meta has the full lead lifecycle before
        // the later "converted" stage is considered valid.
        if ($this->meta_capi->is_configured()) {
            $saved = $this->meta_leads_model->find_by_leadgen_id($leadgen_id);

            if ($saved && empty($saved['capi_lead_event_sent']) && $this->meta_capi->send_stage_event($saved, 'crm_lead')) {
                $this->meta_leads_model->mark_capi_event_sent((int) $saved['id'], 'crm_lead');
            }
        }

        json_response(['success' => true]);
    }

    /**
     * Fetch full lead field data from the Graph API.
     *
     * @param string $leadgen_id
     *
     * @return array
     */
    private function fetch_lead(string $leadgen_id): array
    {
        $token = $this->meta_conf('META_PAGE_ACCESS_TOKEN');
        $version = $this->meta_conf('META_GRAPH_VERSION', 'v22.0');

        if ($token === '') {
            throw new RuntimeException('META_PAGE_ACCESS_TOKEN is not configured.');
        }

        $url =
            'https://graph.facebook.com/' .
            $version .
            '/' .
            rawurlencode($leadgen_id) .
            '?fields=field_data,created_time,form_id,ad_id&access_token=' .
            rawurlencode($token);

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            error_log(
                '[meta-webhook] Graph API cURL error for lead ' .
                    $leadgen_id .
                    ' (token length ' .
                    strlen($token) .
                    '): ' .
                    $curlError,
            );

            throw new RuntimeException('Graph API cURL error: ' . $curlError);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log(
                '[meta-webhook] Graph API error for lead ' .
                    $leadgen_id .
                    ' (HTTP ' .
                    $httpCode .
                    ', token length ' .
                    strlen($token) .
                    '): ' .
                    $response,
            );

            throw new RuntimeException('Graph API returned ' . $httpCode . ': ' . substr((string) $response, 0, 500));
        }

        return json_decode($response, true) ?: [];
    }

    /**
     * Map a Graph API lead response into a meta_leads record.
     *
     * Field-name mapping is best-effort because Instant Forms can have custom
     * field names; the raw field_data is preserved in form_fields regardless.
     *
     * @param string $leadgen_id
     * @param array $lead_data
     *
     * @return array
     */
    private function map_lead(string $leadgen_id, array $lead_data): array
    {
        $fields = [];

        foreach ($lead_data['field_data'] ?? [] as $field) {
            $name = $field['name'] ?? '';
            $values = $field['values'] ?? [];

            $fields[$name] = $values[0] ?? '';
        }

        $first_name = $this->field_value($fields, ['first_name', 'first name', 'prenume']);
        $last_name = $this->field_value($fields, ['last_name', 'last name', 'nume']);
        $full_name = $this->field_value($fields, ['full_name', 'full name', 'name', 'nume_complet']);

        // Fall back to splitting a single "full name" field.
        if (($first_name === '' || $last_name === '') && $full_name !== '') {
            $parts = preg_split('/\s+/', trim($full_name), 2);

            $first_name = $first_name !== '' ? $first_name : ($parts[0] ?? '');
            $last_name = $last_name !== '' ? $last_name : ($parts[1] ?? '');
        }

        $email = $this->field_value($fields, ['email', 'e-mail', 'adresa_email']);
        $raw_phone = $this->field_value($fields, ['phone_number', 'phone', 'telefon', 'mobile_number']);

        $normalized_phone = normalize_international_phone($raw_phone);

        return [
            'leadgen_id' => $leadgen_id,
            'page_id' => (string) ($lead_data['page_id'] ?? ''),
            'form_id' => (string) ($lead_data['form_id'] ?? ''),
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone_number' => $normalized_phone !== null ? '+' . $normalized_phone : '',
            'form_fields' => json_encode($lead_data['field_data'] ?? []),
            'status' => 'new',
            'received_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Return the first non-empty field value for any of the provided field names.
     *
     * @param array $fields
     * @param array $keys
     *
     * @return string
     */
    private function field_value(array $fields, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($fields[$key]) && trim((string) $fields[$key]) !== '') {
                return trim((string) $fields[$key]);
            }
        }

        return '';
    }

    /**
     * Read a value from an environment variable or from the Config class.
     *
     * @param string $name The environment variable / Config constant name.
     * @param string $default Default value when neither source is set.
     *
     * @return string
     */
    private function meta_conf(string $name, string $default = ''): string
    {
        $value = getenv($name);

        if ($value !== false && $value !== '') {
            return $value;
        }

        if (defined("Config::$name")) {
            $value = constant("Config::$name");

            return $value !== '' && $value !== null ? (string) $value : $default;
        }

        return $default;
    }
}
