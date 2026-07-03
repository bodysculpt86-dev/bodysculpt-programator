<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * WhatsApp QR controller for Evolution API integration.
 * ---------------------------------------------------------------------------- */

/**
 * Whatsapp_qr controller.
 *
 * Handles server-to-server communication with Evolution API for WhatsApp
 * connection status and QR code generation.
 *
 * @package Controllers
 */
class Whatsapp_qr extends EA_Controller
{
    /**
     * Whatsapp_qr constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('accounts');
    }

    /**
     * Ensure the current user is an authenticated admin.
     *
     * @return bool
     */
    private function guard_admin(): bool
    {
        if (session('role_slug') !== DB_SLUG_ADMIN) {
            if (session('user_id')) {
                abort(403, 'Forbidden');
            } else {
                redirect('login');
            }

            return false;
        }

        return true;
    }

    /**
     * Make a generic server-to-server call to the Evolution API.
     *
     * @param string $method HTTP method.
     * @param string $path API path (will be appended to EVOLUTION_API_URL).
     *
     * @return array
     */
    private function evo_call(string $method, string $path): array
    {
        $url = rtrim(Config::EVOLUTION_API_URL, '/') . $path;

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'apikey: ' . Config::EVOLUTION_API_KEY,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => 'cURL error: ' . $curlError, 'http' => 0];
        }

        $decoded = json_decode($response, true);

        return [
            'ok' => ($httpCode >= 200 && $httpCode < 300),
            'http' => $httpCode,
            'data' => $decoded,
        ];
    }

    /**
     * Render the WhatsApp QR admin page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('whatsapp_qr')]);

        if (!$this->guard_admin()) {
            return;
        }

        html_vars([
            'page_title' => lang('whatsapp_qr'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name(session('user_id')),
        ]);

        $this->load->view('pages/whatsapp_qr');
    }

    /**
     * Return a base64 QR code for scanning.
     */
    public function qr(): void
    {
        if (!$this->guard_admin()) {
            return;
        }

        $res = $this->evo_call('GET', '/instance/connect/' . rawurlencode(Config::EVOLUTION_INSTANCE));

        json_response([
            'success' => $res['ok'],
            'base64' => $res['data']['base64'] ?? null,
            'code' => $res['data']['code'] ?? null,
            'raw' => $res['data'] ?? null,
        ]);
    }

    /**
     * Return the current connection state for polling.
     */
    public function status(): void
    {
        if (!$this->guard_admin()) {
            return;
        }

        $res = $this->evo_call('GET', '/instance/connectionState/' . rawurlencode(Config::EVOLUTION_INSTANCE));

        $state = $res['data']['instance']['state'] ?? ($res['data']['state'] ?? 'unknown');

        json_response(['success' => $res['ok'], 'state' => $state]);
    }
}
