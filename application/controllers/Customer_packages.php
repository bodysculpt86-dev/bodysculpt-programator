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
 * Customer packages controller.
 *
 * Handles the sale and management of subscription packages sold to customers.
 *
 * @package Controllers
 */
class Customer_packages extends EA_Controller
{
    /**
     * Customer packages constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('customer_packages_model');
        $this->load->model('customer_package_items_model');
        $this->load->model('packages_model');
        $this->load->model('roles_model');

        $this->load->library('accounts');
    }

    /**
     * Render the backend customer packages page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('customer_packages')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_CUSTOMER_PACKAGES)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');

        $packages = $this->packages_model->to_options();

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'available_packages' => $packages,
        ]);

        html_vars([
            'page_title' => lang('customer_packages'),
            'active_menu' => PRIV_CUSTOMER_PACKAGES,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'privileges' => $this->roles_model->get_permissions_by_slug($role_slug),
            'available_packages' => $packages,
        ]);

        $this->load->view('pages/customer_packages');
    }

    /**
     * Filter sold customer packages.
     */
    public function search(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_CUSTOMER_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            check('keyword', 'string|null');
            check('is_active', 'bool|int|null');
            check('order_by', 'string|null');
            check('limit', 'numeric|null');
            check('offset', 'numeric|null');

            $keyword = request('keyword', '');
            $is_active = request('is_active');
            $order_by = request('order_by', 'update_datetime DESC');
            $limit = request('limit', 1000);
            $offset = (int) request('offset', '0');

            $is_active = $is_active !== null && $is_active !== '' ? (bool) (int) $is_active : null;

            $customer_packages = $this->customer_packages_model->search(
                $keyword,
                $is_active,
                $limit,
                $offset,
                $order_by,
            );

            json_response($customer_packages);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Sell a package to a customer.
     */
    public function store(): void
    {
        try {
            method('post');

            if (cannot('add', PRIV_CUSTOMER_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            check('customer_id', 'numeric');
            check('package_id', 'numeric');
            check('notes', 'string|null');

            $customer_id = (int) request('customer_id');
            $package_id = (int) request('package_id');
            $notes = request('notes') ?: null;

            $customer_package_id = $this->customer_packages_model->sell($customer_id, $package_id, $notes);

            $customer_package = $this->customer_packages_model->find($customer_package_id);

            json_response([
                'success' => true,
                'id' => $customer_package_id,
                'customer_package' => $customer_package,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Find a sold customer package.
     */
    public function find(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_CUSTOMER_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            check('customer_package_id', 'numeric');

            $customer_package_id = (int) request('customer_package_id');

            if ($customer_package_id <= 0) {
                throw new InvalidArgumentException('Invalid customer package ID provided.');
            }

            $customer_package = $this->customer_packages_model->find($customer_package_id);

            json_response($customer_package);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Update a sold customer package (manual item adjustments).
     */
    public function update(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_CUSTOMER_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            check('customer_package_id', 'numeric');
            check('items', 'array');

            $customer_package_id = (int) request('customer_package_id');
            $items = request('items');
            $modified_by_user_id = session('user_id');

            foreach ($items as $item) {
                if (empty($item['id']) || !is_numeric($item['id'])) {
                    throw new InvalidArgumentException('Invalid customer package item ID.');
                }

                if (!isset($item['quantity_remaining']) || !is_numeric($item['quantity_remaining'])) {
                    throw new InvalidArgumentException('Invalid quantity remaining value.');
                }

                $this->customer_package_items_model->adjust(
                    (int) $item['id'],
                    (int) $item['quantity_remaining'],
                    $item['reason'] ?? null,
                    $modified_by_user_id ? (int) $modified_by_user_id : null,
                );
            }

            $this->customer_packages_model->recalculate_status($customer_package_id);

            $customer_package = $this->customer_packages_model->find($customer_package_id);

            json_response([
                'success' => true,
                'id' => $customer_package_id,
                'customer_package' => $customer_package,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Remove a sold customer package.
     */
    public function destroy(): void
    {
        try {
            method('post');

            if (cannot('delete', PRIV_CUSTOMER_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            check('customer_package_id', 'numeric');

            $customer_package_id = (int) request('customer_package_id');

            if ($customer_package_id <= 0) {
                throw new InvalidArgumentException('Invalid customer package ID provided.');
            }

            $this->customer_packages_model->delete($customer_package_id);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
