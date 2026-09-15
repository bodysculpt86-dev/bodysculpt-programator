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

            // event_name is 'LEADS' (funnel stage 1); the lowercase 'crm_lead'
            // below only selects the capi_lead_event_sent column.
            if ($saved && empty($saved['capi_lead_event_sent']) && $this->meta_capi->send_stage_event($saved, 'LEADS')) {
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

    /**
     * Back-fill missed Meta leads (CLI only, one-off).
     *
     * Imports leads that were submitted BEFORE the app was granted Leads Access
     * on the Page and therefore never reached the leadgen webhook. Lists every
     * Instant Form on the Page, pages through each form's leads, and imports
     * only those with created_time >= `--since` (default: today in the clinic's
     * timezone). Dedupes on leadgen_id, so re-running is a safe no-op.
     *
     * Reuses the exact map_lead() + meta_leads_model->save() path as receive(),
     * so a back-filled lead is identical to a webhook lead except received_at is
     * the lead's real created_time (not the import run time).
     *
     * Usage:
     *
     *   php index.php webhooks_meta backfill
     *   php index.php webhooks_meta backfill --since=2026-09-04
     *   php index.php webhooks_meta backfill --since=2026-09-04 --page-id=105976218886400
     */
    public function backfill(): void
    {
        if (!is_cli()) {
            exit('This command can only be run from the command line.' . PHP_EOL);
        }

        $token = $this->meta_conf('META_PAGE_ACCESS_TOKEN');

        if ($token === '') {
            fwrite(STDERR, '[meta-backfill] META_PAGE_ACCESS_TOKEN is not configured.' . PHP_EOL);
            exit(1);
        }

        $page_id = $this->cli_option('--page-id', $this->meta_conf('META_PAGE_ID', '105976218886400'));
        $since = $this->cli_option('--since', date('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
            fwrite(STDERR, '[meta-backfill] Invalid --since "' . $since . '"; expected YYYY-MM-DD.' . PHP_EOL);
            exit(1);
        }

        // "since" is a calendar day in the clinic's timezone; Meta returns
        // created_time in UTC, so convert the start-of-day boundary to a UTC
        // timestamp for comparison (mirrors Console::send_sms_reminders()).
        $timezone = new DateTimeZone('Europe/Bucharest');

        try {
            $timezone = new DateTimeZone(setting('default_timezone') ?: 'Europe/Bucharest');
        } catch (Throwable $e) {
            log_message('warning', '[meta-backfill] Invalid default timezone, using Europe/Bucharest: ' . $e->getMessage());
        }

        $cutoff = (new DateTime($since . ' 00:00:00', $timezone))->getTimestamp();

        try {
            $forms = $this->graph_get_json($page_id . '/leadgen_forms', ['fields' => 'id,name,status']);
        } catch (Throwable $e) {
            fwrite(STDERR, '[meta-backfill] FATAL: could not list leadgen forms: ' . $e->getMessage() . PHP_EOL);
            exit(1);
        }

        $forms_scanned = 0;
        $leads_seen = 0;
        $in_window = 0;
        $skipped_existing = 0;
        $imported = 0;
        $errors = [];

        foreach ($forms['data'] ?? [] as $form) {
            $form_id = (string) ($form['id'] ?? '');
            $form_name = (string) ($form['name'] ?? $form_id);

            if ($form_id === '') {
                continue;
            }

            $forms_scanned++;

            try {
                $after = null;

                do {
                    $params = ['fields' => 'id,created_time,field_data,form_id', 'limit' => 100];

                    if ($after !== null) {
                        $params['after'] = $after;
                    }

                    $page = $this->graph_get_json($form_id . '/leads', $params);

                    foreach ($page['data'] ?? [] as $lead) {
                        $leads_seen++;

                        try {
                            $leadgen_id = (string) ($lead['id'] ?? '');
                            $created_time = (string) ($lead['created_time'] ?? '');

                            if ($leadgen_id === '' || $created_time === '') {
                                $errors[] = 'form "' . $form_name . '": lead missing id/created_time; skipped';
                                continue;
                            }

                            $created_ts = strtotime($created_time);

                            if ($created_ts === false) {
                                $errors[] = 'lead ' . $leadgen_id . ': unparseable created_time "' . $created_time . '"; skipped';
                                continue;
                            }

                            // The critical filter: never import leads older than
                            // the window (the two forms hold ~995 total leads).
                            if ($created_ts < $cutoff) {
                                continue;
                            }

                            $in_window++;

                            if ($this->meta_leads_model->find_by_leadgen_id($leadgen_id)) {
                                $skipped_existing++;
                                continue;
                            }

                            // Reuse the webhook's exact mapping (field extraction,
                            // full_name split, phone normalization, form_fields JSON).
                            $lead_data = [
                                'field_data' => $lead['field_data'] ?? [],
                                'form_id' => $form_id,
                                'page_id' => $page_id,
                            ];

                            $mapped = $this->map_lead($leadgen_id, $lead_data);

                            // Back-fill correctness: stamp the lead with when it
                            // actually arrived, not when this import ran.
                            $mapped['received_at'] = $this->format_lead_time($created_time);

                            $this->meta_leads_model->save($mapped);
                            $imported++;

                            // Mirror receive(): fire the initial CRM stage so the
                            // Conversions API feedback loop still sees these leads.
                            if ($this->meta_capi->is_configured()) {
                                $saved = $this->meta_leads_model->find_by_leadgen_id($leadgen_id);

                                // event_name is 'LEADS' (funnel stage 1); the lowercase 'crm_lead'
                                // below only selects the capi_lead_event_sent column.
                                if ($saved && empty($saved['capi_lead_event_sent']) && $this->meta_capi->send_stage_event($saved, 'LEADS')) {
                                    $this->meta_leads_model->mark_capi_event_sent((int) $saved['id'], 'crm_lead');
                                }
                            }
                        } catch (Throwable $e) {
                            $errors[] = 'lead ' . ($lead['id'] ?? '?') . ': ' . $e->getMessage();
                        }
                    }

                    $after = $page['paging']['cursors']['after'] ?? null;
                } while ($after !== null);
            } catch (Throwable $e) {
                $errors[] = 'form "' . $form_name . '": ' . $e->getMessage();
            }
        }

        echo PHP_EOL;
        echo '=== Meta lead back-fill summary ===' . PHP_EOL;
        echo 'Page: ' . $page_id . PHP_EOL;
        echo 'Window: since ' . $since . ' 00:00:00 ' . $timezone->getName() . PHP_EOL;
        echo 'Instant Forms scanned: ' . $forms_scanned . PHP_EOL;
        echo 'Leads returned by API: ' . $leads_seen . PHP_EOL;
        echo 'Leads in window: ' . $in_window . PHP_EOL;
        echo 'Already present (skipped): ' . $skipped_existing . PHP_EOL;
        echo 'Newly imported: ' . $imported . PHP_EOL;

        if ($errors !== []) {
            echo 'Errors: ' . count($errors) . PHP_EOL;

            foreach ($errors as $error) {
                echo '  - ' . $error . PHP_EOL;
            }
        }

        echo PHP_EOL;
    }

    /**
     * Read a "--name=value" argument from the CLI argv.
     *
     * @param string $name Option name without the leading dashes.
     * @param string $default Value returned when the option is absent.
     *
     * @return string
     */
    private function cli_option(string $name, string $default = ''): string
    {
        $prefix = '--' . $name . '=';

        foreach (($GLOBALS['argv'] ?? []) as $arg) {
            if (str_starts_with((string) $arg, $prefix)) {
                return substr((string) $arg, strlen($prefix));
            }
        }

        return $default;
    }

    /**
     * GET a Graph API edge and return its decoded JSON.
     *
     * @param string $endpoint Edge path relative to the Graph version (e.g. "{form-id}/leads").
     * @param array $params Query parameters (access_token is appended automatically).
     *
     * @return array
     */
    private function graph_get_json(string $endpoint, array $params): array
    {
        $token = $this->meta_conf('META_PAGE_ACCESS_TOKEN');
        $version = $this->meta_conf('META_GRAPH_VERSION', 'v22.0');

        $params['access_token'] = $token;

        $url = 'https://graph.facebook.com/' . $version . '/' . $endpoint . '?' . http_build_query($params);

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            error_log('[meta-backfill] Graph API cURL error: ' . $curlError);
            throw new RuntimeException('Graph API cURL error: ' . $curlError);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('[meta-backfill] Graph API returned ' . $httpCode . ': ' . substr((string) $response, 0, 500));
            throw new RuntimeException('Graph API returned ' . $httpCode . ': ' . substr((string) $response, 0, 500));
        }

        return json_decode($response, true) ?: [];
    }

    /**
     * Convert a Meta created_time (UTC) into a "Y-m-d H:i:s" string in the app's
     * configured timezone, matching the value the webhook stores via date().
     *
     * @param string $created_time
     *
     * @return string
     */
    private function format_lead_time(string $created_time): string
    {
        $dt = new DateTime($created_time);

        $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));

        return $dt->format('Y-m-d H:i:s');
    }
}
