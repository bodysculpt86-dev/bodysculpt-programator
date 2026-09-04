<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Meta Conversions API for CRM (lead ads feedback loop).
 * ---------------------------------------------------------------------------- */

/**
 * Meta CAPI library.
 *
 * Sends server-to-server lead lifecycle stage events to the clinic's Meta
 * Dataset/Pixel so that Meta's "Conversion Leads" optimization can learn which
 * leads actually became customers.
 *
 * This is a machine-learning signal — it does NOT create a visible status
 * toggle inside Meta's own lead-list UI.
 *
 * Config-gated: when META_DATASET_ID / META_CRM_ACCESS_TOKEN are absent the
 * library reports "not configured" and every send is a graceful no-op, so the
 * lead ingestion feature works without the feedback loop enabled.
 */
class Meta_capi
{
    /**
     * Read an environment variable with an optional default, falling back to
     * the Config class constants (mirrors Whatsapp_qr::evo_conf()).
     *
     * @param string $key Environment variable / Config constant name.
     * @param string $default Default value when neither source is set.
     *
     * @return string
     */
    private function meta_conf(string $key, string $default = ''): string
    {
        $value = getenv($key);

        if ($value !== false && $value !== '') {
            return $value;
        }

        $configConstant = "Config::$key";

        if (defined($configConstant)) {
            $configValue = constant($configConstant);

            if ($configValue !== '' && $configValue !== null) {
                return (string) $configValue;
            }
        }

        return $default;
    }

    /**
     * Whether the Conversions API integration is configured.
     *
     * @return bool
     */
    public function is_configured(): bool
    {
        return $this->meta_conf('META_DATASET_ID') !== '' && $this->meta_conf('META_CRM_ACCESS_TOKEN') !== '';
    }

    /**
     * Send a single lead lifecycle stage event to the Conversions API.
     *
     * @param array $lead A meta_leads record (must include leadgen_id).
     * @param string $stage Stage name ('crm_lead', 'converted', ...).
     *
     * @return bool True when the event was accepted by Meta.
     */
    public function send_stage_event(array $lead, string $stage): bool
    {
        if (!$this->is_configured() || empty($lead['leadgen_id'])) {
            return false;
        }

        $dataset_id = $this->meta_conf('META_DATASET_ID');
        $token = $this->meta_conf('META_CRM_ACCESS_TOKEN');
        $version = $this->meta_conf('META_GRAPH_VERSION', 'v22.0');

        $url =
            'https://graph.facebook.com/' .
            $version .
            '/' .
            rawurlencode($dataset_id) .
            '/events?access_token=' .
            rawurlencode($token);

        $payload = json_encode([
            'data' => [$this->build_event($lead, $stage)],
        ]);

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            log_message(
                'error',
                '[meta-capi] cURL error sending "' . $stage . '" for lead ' . $lead['leadgen_id'] . ': ' . $curlError,
            );

            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            log_message(
                'error',
                '[meta-capi] Graph API returned ' .
                    $httpCode .
                    ' for "' .
                    $stage .
                    '" lead ' .
                    $lead['leadgen_id'] .
                    ': ' .
                    substr((string) $response, 0, 500),
            );

            return false;
        }

        log_message('debug', '[meta-capi] Sent "' . $stage . '" event for lead ' . $lead['leadgen_id']);

        return true;
    }

    /**
     * Build a single Conversions API event.
     *
     * @param array $lead
     * @param string $stage
     *
     * @return array
     */
    private function build_event(array $lead, string $stage): array
    {
        $phone_digits = preg_replace('/\D/', '', (string) ($lead['phone_number'] ?? ''));

        return [
            'event_name' => $stage,
            'event_time' => time(),
            // Stable id so a retry of the same stage is deduplicated by Meta.
            'event_id' => sha1((string) ($lead['leadgen_id'] ?? '') . ':' . $stage),
            'action_source' => 'other',
            'user_data' => [
                'lead_id' => $lead['leadgen_id'],
                'em' => $this->hash_value($lead['email'] ?? null),
                'ph' => $phone_digits !== '' ? hash('sha256', $phone_digits) : null,
                'fn' => $this->hash_value($lead['first_name'] ?? null),
                'ln' => $this->hash_value($lead['last_name'] ?? null),
            ],
        ];
    }

    /**
     * SHA-256 hash a PII value using Meta's normalization (trim + lowercase).
     *
     * @param string|null $value
     *
     * @return string|null
     */
    private function hash_value(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        return hash('sha256', $normalized);
    }
}
