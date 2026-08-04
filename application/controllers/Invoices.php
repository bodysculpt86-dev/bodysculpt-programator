<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.6.0
 * ---------------------------------------------------------------------------- */

/**
 * Invoices controller.
 *
 * Handles the standalone fiscal invoicing page (SmartBill / e-Factura).
 *
 * Access is gated by the PACKAGES privilege on purpose: the same roles that
 * manage packages/abonamente also issue invoices, so no new roles column is
 * required. If invoicing ever needs its own permission set, add an
 * "invoices" column to ea_roles (migration) + a PRIV_INVOICES constant and
 * swap the privilege checks below.
 *
 * @package Controllers
 */
class Invoices extends EA_Controller
{
    public array $allowed_client_fields = [
        'id',
        'type',
        'name',
        'cui',
        'reg_com',
        'address',
        'city',
        'county',
        'email',
        'phone',
    ];

    /**
     * Invoices constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('roles_model');
        $this->load->model('billing_clients_model');
        $this->load->model('services_model');
        $this->load->model('packages_model');
        $this->load->model('invoices_model');
        $this->load->model('customers_model');
        $this->load->model('invoice_links_model');

        $this->load->library('accounts');
        $this->load->library('anaf_lookup');
        $this->load->library('smartbill');
        $this->load->library('whatsapp_flaxxa');
    }

    /**
     * Render the backend invoices page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('invoices')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_PACKAGES)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');

        $vat_default = $this->readEnvOrConfig('VAT_DEFAULT');

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'currency' => setting('currency'),
            // NOTE: '0' is a valid VAT rate - do NOT use ?: / empty() here.
            'vat_default' => $vat_default !== null && $vat_default !== '' ? $vat_default : '19',
            'smartbill_configured' => $this->smartbill->is_configured(),
            'can_issue_invoices' => $this->can_issue_invoices(),
        ]);

        html_vars([
            'page_title' => lang('invoices'),
            'active_menu' => 'invoices',
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'privileges' => $this->roles_model->get_permissions_by_slug($role_slug),
        ]);

        $this->load->view('pages/invoices');
    }

    /**
     * Search billing clients by name or CUI.
     *
     * POST invoices/search_clients
     */
    public function search_clients(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            check('keyword', 'string|null');

            $keyword = request('keyword', '');

            // Fiscal clients first (ea_billing_clients), then booking customers
            // (ea_users) — selecting a booking customer pre-fills the new-client
            // form so the staff saves them as a fiscal client in one click.
            $results = [];

            foreach ($this->billing_clients_model->search($keyword) as $billing_client) {
                $results[] = [
                    'source' => 'billing',
                    'id' => (int) $billing_client['id'],
                    'name' => $billing_client['name'],
                    'cui' => $billing_client['cui'],
                    'type' => $billing_client['type'],
                ];
            }

            foreach ($this->customers_model->search($keyword, 10, 0, 'first_name ASC') as $customer) {
                $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $results[] = [
                    'source' => 'customer',
                    'id' => (int) $customer['id'],
                    'name' => $name,
                    'cui' => null,
                    'type' => 'pf',
                    'email' => $customer['email'] ?? '',
                    'phone' => $customer['phone_number'] ?? ($customer['mobile_number'] ?? ''),
                    'address' => $customer['address'] ?? '',
                    'city' => $customer['city'] ?? '',
                ];
            }

            json_response($results);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Look up a company by CUI in the ANAF public registry.
     *
     * POST invoices/lookup_cui
     */
    public function lookup_cui(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            check('cui', 'string');

            json_response($this->anaf_lookup->lookup((string) request('cui')));
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * List the active services as invoice line catalog entries.
     *
     * POST invoices/list_services
     */
    public function list_services(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            $services = $this->services_model->get(null, null, null, 'name ASC');

            $catalog = array_map(
                fn($service) => [
                    'id' => (int) $service['id'],
                    'name' => $service['name'],
                    'price' => $service['price'],
                ],
                $services,
            );

            json_response($catalog);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * List the active packages (abonamente) as invoice line catalog entries.
     *
     * POST invoices/list_packages
     */
    public function list_packages(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            $packages = $this->packages_model->search('', null, true, 1000, 0, 'name ASC');

            $catalog = array_map(
                fn($package) => [
                    'id' => (int) $package['id'],
                    'name' => $package['name'],
                    'price' => $package['price'],
                ],
                $packages,
            );

            json_response($catalog);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Save (create or update) a billing client.
     *
     * POST invoices/save_client
     */
    public function save_client(): void
    {
        try {
            method('post');

            check('client', 'array');

            if (!$this->can_issue_invoices()) {
                abort(403, 'Forbidden');
            }

            $client = array_intersect_key((array) request('client'), array_flip($this->allowed_client_fields));

            $client_id = $this->billing_clients_model->save($client);

            json_response([
                'success' => true,
                'id' => $client_id,
                'client' => $this->billing_clients_model->find($client_id),
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Issue an invoice via SmartBill (or as a draft when is_draft is set).
     *
     * POST invoices/issue
     *
     * Creates a REAL fiscal document - restricted to admins only. Protected by:
     *  - a client-side double-submit guard (button disabled on click), and
     *  - server-side idempotency: the request carries an idempotency_key
     *    generated per modal open; a retried submission returns the already
     *    created invoice instead of issuing a second one.
     */
    public function issue(): void
    {
        try {
            method('post');

            if (!$this->can_issue_invoices()) {
                abort(403, 'Forbidden');
            }

            check('billing_client_id', 'numeric');
            check('issue_date', 'date');
            check('payment_method', 'string|null');
            check('is_draft', 'bool|int|string|null');
            check('idempotency_key', 'string|null');
            check('lines', 'array');

            $billing_client_id = (int) request('billing_client_id');
            $issue_date = request('issue_date');
            $payment_method = request('payment_method');
            $is_draft = filter_var(request('is_draft'), FILTER_VALIDATE_BOOLEAN);
            $idempotency_key = substr((string) request('idempotency_key', ''), 0, 64);
            $lines = (array) request('lines');

            if (empty($lines)) {
                throw new InvalidArgumentException('The invoice has no lines.');
            }

            $client = null;

            try {
                $client = $this->billing_clients_model->find($billing_client_id);
            } catch (InvalidArgumentException $e) {
                // Consistency net: the id may belong to a booking customer
                // (ea_users) instead of a fiscal client (ea_billing_clients) -
                // e.g. a stale cached JS sends the customer id directly. Create
                // the fiscal client from the customer once, then proceed.
                $customer = $this->customers_model->find($billing_client_id);

                $customer_name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));

                if ($customer_name === '') {
                    throw $e;
                }

                $customer_email = $customer['email'] ?? null;
                $customer_phone = $customer['phone_number'] ?? ($customer['mobile_number'] ?? null);

                // Find-or-create: reuse an existing fiscal client for the same
                // person instead of accumulating duplicates on every invoice.
                $existing_client = $this->billing_clients_model->find_by_identity(
                    $customer_name,
                    $customer_email,
                    $customer_phone,
                );

                if ($existing_client !== null) {
                    $billing_client_id = (int) $existing_client['id'];
                    $client = $existing_client;
                } else {
                    $billing_client_id = $this->billing_clients_model->save([
                        'type' => 'pf',
                        'name' => $customer_name,
                        'cui' => null,
                        'reg_com' => null,
                        'address' => $customer['address'] ?? null,
                        'city' => $customer['city'] ?? null,
                        'county' => null,
                        'email' => $customer_email,
                        'phone' => $customer_phone,
                    ]);

                    $client = $this->billing_clients_model->find($billing_client_id);

                    log_message(
                        'debug',
                        '[invoices] Auto-created billing client #' . $billing_client_id . ' from booking customer #' . $customer['id'],
                    );
                }
            }

            // Server-side idempotency: same key -> return the existing invoice
            // instead of issuing a duplicate fiscal document.
            $existing = $this->invoices_model->find_by_idempotency_key($idempotency_key);

            if ($existing !== null) {
                json_response([
                    'success' => $existing['smartbill_status'] === 'issued',
                    'duplicate' => true,
                    'invoice' => $existing,
                ]);

                return;
            }

            // Recompute everything server-side; never trust client totals.
            $allowed_source_types = ['service', 'package', 'product', 'manual'];

            $items = [];
            $subtotal = 0.0;
            $vat_total = 0.0;

            foreach ($lines as $line) {
                $description = trim((string) ($line['description'] ?? ''));
                $qty = (float) ($line['qty'] ?? 0);
                $unit_price = (float) ($line['unit_price'] ?? 0);
                $vat_rate = (float) ($line['vat_rate'] ?? 0);

                if ($description === '' || $qty <= 0 || $unit_price < 0 || $vat_rate < 0) {
                    throw new InvalidArgumentException('Invalid invoice line.');
                }

                $source_type = in_array($line['source_type'] ?? '', $allowed_source_types, true)
                    ? $line['source_type']
                    : 'manual';

                $line_total = round($qty * $unit_price, 2);

                $subtotal += $line_total;
                $vat_total += round(($line_total * $vat_rate) / 100, 2);

                $items[] = [
                    'source_type' => $source_type,
                    'source_id' => !empty($line['source_id']) ? (int) $line['source_id'] : null,
                    'description' => substr($description, 0, 256),
                    'qty' => $qty,
                    'unit_price' => $unit_price,
                    'vat_rate' => $vat_rate,
                    'line_total' => $line_total,
                ];
            }

            $invoice_id = $this->invoices_model->create_pending(
                [
                    'billing_client_id' => $billing_client_id,
                    'issue_date' => $issue_date,
                    'payment_method' => $payment_method ?: null,
                    'subtotal' => round($subtotal, 2),
                    'vat_total' => round($vat_total, 2),
                    'total' => round($subtotal + $vat_total, 2),
                    'is_draft' => $is_draft ? 1 : 0,
                    'idempotency_key' => $idempotency_key !== '' ? $idempotency_key : null,
                    'created_by' => (int) session('user_id') ?: null,
                ],
                $items,
            );

            if (!$this->smartbill->is_configured()) {
                $this->invoices_model->mark_failed($invoice_id, 'SmartBill is not configured (SMARTBILL_* env vars missing).');

                json_response([
                    'success' => false,
                    'error' => 'smartbill_not_configured',
                    'invoice_id' => $invoice_id,
                ]);

                return;
            }

            $payload = $this->smartbill->build_invoice_payload($client, $items, [
                'issue_date' => $issue_date,
                'is_draft' => $is_draft,
                'payment_method' => $payment_method,
                'total' => round($subtotal + $vat_total, 2),
            ]);

            $result = $this->smartbill->create_invoice($payload);

            if (!$result['success']) {
                $this->invoices_model->mark_failed($invoice_id, (string) $result['error']);

                json_response([
                    'success' => false,
                    'error' => 'smartbill_error',
                    'message' => $result['error'],
                    'invoice_id' => $invoice_id,
                ]);

                return;
            }

            $this->invoices_model->mark_issued($invoice_id, $result['series'], $result['number'], $result['message']);

            log_message(
                'debug',
                '[invoices] Invoice #' . $invoice_id . ' issued in SmartBill as '
                    . $result['series'] . $result['number'] . ($is_draft ? ' (DRAFT)' : ''),
            );

            json_response([
                'success' => true,
                'invoice' => $this->invoices_model->find($invoice_id),
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * List issued invoices for the history table.
     *
     * POST invoices/history
     */
    public function history(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            json_response($this->invoices_model->get_history());
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Stream the SmartBill PDF of an issued invoice.
     *
     * GET invoices/pdf/<invoice_id>
     *
     * @param int $invoice_id
     */
    public function pdf(int $invoice_id = 0): void
    {
        method('get');

        if (cannot('view', PRIV_PACKAGES)) {
            abort(403, 'Forbidden');
        }

        $invoice = $this->invoices_model->find($invoice_id);

        if ($invoice['smartbill_status'] !== 'issued' || empty($invoice['series']) || empty($invoice['number'])) {
            show_404();

            return;
        }

        $pdf = $this->smartbill->get_invoice_pdf($invoice['series'], $invoice['number']);

        if ($pdf === null) {
            show_404();

            return;
        }

        $this->output
            ->set_content_type('application/pdf')
            ->set_header('Content-Disposition: inline; filename="factura-' . $invoice['series'] . $invoice['number'] . '.pdf"')
            ->set_output($pdf);
    }

    /**
     * Whether the current user may issue invoices / manage fiscal clients.
     *
     * The admin role is always required. When INVOICE_ALLOWED_USERS is
     * populated (comma-separated usernames), the current user's username must
     * also be on the list (trimmed, case-insensitive). An EMPTY/unset list
     * falls back to the previous behavior: any admin can emit. Nobody is
     * ever locked out by an empty list.
     *
     * @return bool
     */
    private function can_issue_invoices(): bool
    {
        if (session('role_slug') !== DB_SLUG_ADMIN) {
            return false;
        }

        // The env-managed super-admin is NEVER restricted by the allowlist
        // (mirrors Accounts::is_super_admin_email()).
        $super_admin_email = getenv('SUPERADMIN_EMAIL');

        if (!empty($super_admin_email)) {
            $user = $this->db->get_where('users', ['id' => (int) session('user_id')])->row_array();

            if (strcasecmp(trim((string) ($user['email'] ?? '')), trim($super_admin_email)) === 0) {
                return true;
            }
        }

        $allowed = $this->readEnvOrConfig('INVOICE_ALLOWED_USERS');

        if ($allowed === null || trim($allowed) === '') {
            return true;
        }

        $allowed_usernames = array_filter(
            array_map(fn($username) => mb_strtolower(trim($username)), explode(',', $allowed)),
        );

        // Usernames live in ea_user_settings (unique, enforced by
        // Users_model::validate_username on every save).
        $settings = $this->db->get_where('user_settings', ['id_users' => (int) session('user_id')])->row_array();

        $username = mb_strtolower(trim((string) ($settings['username'] ?? '')));

        return $username !== '' && in_array($username, $allowed_usernames, true);
    }

    /**
     * Send the invoice PDF to the billing client on WhatsApp (document
     * template with a public short link Meta fetches at send time).
     *
     * POST invoices/send_whatsapp
     */
    public function send_whatsapp(): void
    {
        try {
            method('post');

            if (!$this->can_issue_invoices()) {
                abort(403, 'Forbidden');
            }

            check('invoice_id', 'numeric');

            $invoice_id = (int) request('invoice_id');

            $invoice = $this->invoices_model->find($invoice_id);

            if ($invoice['smartbill_status'] !== 'issued' || empty($invoice['series']) || empty($invoice['number'])) {
                json_response(['success' => false, 'error' => 'invoice_not_issued']);

                return;
            }

            if (empty($invoice['billing_client_id'])) {
                json_response(['success' => false, 'error' => 'no_client']);

                return;
            }

            $client = $this->billing_clients_model->find((int) $invoice['billing_client_id']);

            if (trim((string) ($client['phone'] ?? '')) === '') {
                json_response(['success' => false, 'error' => 'no_phone']);

                return;
            }

            // Public short link for the PDF (Meta fetches it at send time).
            $pdf_url = site_url('inv/' . $this->invoice_links_model->create($invoice_id));

            $result = $this->whatsapp_flaxxa->send_invoice_pdf($client, $invoice, $pdf_url);

            json_response($result);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Read a value from an environment variable or from the Config class.
     *
     * Mirrors Payments::readEnvOrConfig().
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
