<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * WhatsApp marketing broadcast controller (Flaxxa WAPI).
 * ---------------------------------------------------------------------------- */

/**
 * Marketing controller.
 *
 * Allows admins to send the approved WhatsApp marketing template
 * to all customers with a valid phone number.
 *
 * @package Controllers
 */
class Marketing extends EA_Controller
{
    /**
     * Number of recipients processed per AJAX batch.
     */
    private const BATCH_SIZE = 10;

    /**
     * Delay between two consecutive WhatsApp sends (microseconds).
     */
    private const SEND_DELAY_US = 500000; // 0.5 seconds

    /**
     * Marketing constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('customers_model');
        $this->load->model('users_model');

        $this->load->library('accounts');
        $this->load->library('whatsapp_flaxxa');

        $this->load->helper('phone');
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
     * Render the WhatsApp marketing page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('marketing')]);

        if (!$this->guard_admin()) {
            return;
        }

        html_vars([
            'page_title' => lang('marketing'),
            'active_menu' => 'marketing',
            'user_display_name' => $this->accounts->get_user_display_name(session('user_id')),
        ]);

        $this->load->view('pages/marketing');
    }

    /**
     * Return the number of customers with a valid phone number.
     */
    public function recipients_count(): void
    {
        try {
            method('post');

            if (session('role_slug') !== DB_SLUG_ADMIN) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            json_response([
                'total' => count($this->get_marketing_recipients()),
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Send the marketing template to a batch of customers.
     *
     * Expected POST parameters: offset, procedure, discount, valid_until.
     */
    public function send_batch(): void
    {
        try {
            method('post');

            if (session('role_slug') !== DB_SLUG_ADMIN) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('offset', 'integer');
            check('procedure', 'string');
            check('discount', 'numeric');
            check('valid_until', 'string');

            $offset = (int) request('offset');
            $procedure = trim((string) request('procedure'));
            $discount = trim((string) request('discount'));
            $valid_until = trim((string) request('valid_until'));

            $recipients = $this->get_marketing_recipients();
            $total = count($recipients);

            $batch = array_slice($recipients, $offset, self::BATCH_SIZE);

            $results = [];

            foreach ($batch as $index => $customer) {
                if ($index > 0) {
                    usleep(self::SEND_DELAY_US);
                }

                $send_result = $this->whatsapp_flaxxa->send_marketing($customer, $procedure, $discount, $valid_until);

                $results[] = [
                    'id' => $customer['id'],
                    'name' => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
                    'phone' => $customer['phone_number'] ?? '',
                    'success' => $send_result['success'],
                    'error' => $send_result['error'] ?? null,
                ];
            }

            $processed = $offset + count($batch);

            json_response([
                'results' => $results,
                'processed' => $processed,
                'total' => $total,
                'done' => $processed >= $total,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Send a single test marketing message to a phone number.
     *
     * Expected POST parameters: phone, procedure, discount, valid_until.
     */
    public function send_test(): void
    {
        try {
            method('post');

            if (session('role_slug') !== DB_SLUG_ADMIN) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('phone', 'string');
            check('procedure', 'string');
            check('discount', 'numeric');
            check('valid_until', 'string');

            $phone = trim((string) request('phone'));
            $procedure = trim((string) request('procedure'));
            $discount = trim((string) request('discount'));
            $valid_until = trim((string) request('valid_until'));

            if (normalize_romanian_phone($phone) === null) {
                json_response([
                    'success' => false,
                    'error' => 'invalid_phone',
                ]);

                return;
            }

            // Use the logged-in admin's name so the test message looks realistic.
            $admin = $this->users_model->find(session('user_id'));

            $customer = [
                'id' => null,
                'first_name' => $admin['first_name'] ?? '',
                'last_name' => $admin['last_name'] ?? '',
                'phone_number' => $phone,
            ];

            $result = $this->whatsapp_flaxxa->send_marketing($customer, $procedure, $discount, $valid_until);

            json_response($result);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Get all customers that have a phone number valid for WhatsApp.
     *
     * @return array List of customer arrays (id, first_name, last_name, phone_number).
     */
    private function get_marketing_recipients(): array
    {
        $customers = $this->customers_model
            ->query()
            ->select('id, first_name, last_name, phone_number')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        return array_values(
            array_filter($customers, function (array $customer): bool {
                return normalize_romanian_phone($customer['phone_number'] ?? null) !== null;
            })
        );
    }
}
