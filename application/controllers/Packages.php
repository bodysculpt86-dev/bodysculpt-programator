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
 * Packages controller.
 *
 * Handles the package/subscription bundle related operations.
 *
 * @package Controllers
 */
class Packages extends EA_Controller
{
    public array $allowed_package_fields = [
        'id',
        'name',
        'price',
        'id_service_categories',
        'validity_days',
        'is_active',
        'notes',
        'items',
    ];

    public array $optional_package_fields = [
        'id_service_categories' => null,
        'validity_days' => null,
        'notes' => null,
    ];

    /**
     * Packages constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('packages_model');
        $this->load->model('services_model');
        $this->load->model('service_categories_model');
        $this->load->model('roles_model');

        $this->load->library('accounts');
    }

    /**
     * Render the backend packages page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('packages')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_PACKAGES)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');

        $services = $this->services_model->get(null, null, null, 'name ASC');
        $categories = $this->service_categories_model->to_options();

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'available_services' => $services,
            'service_categories' => $categories,
            'currency' => setting('currency'),
        ]);

        html_vars([
            'page_title' => lang('packages'),
            'active_menu' => PRIV_PACKAGES,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'privileges' => $this->roles_model->get_permissions_by_slug($role_slug),
            'available_services' => $services,
            'service_categories' => $categories,
        ]);

        $this->load->view('pages/packages');
    }

    /**
     * Filter packages by the provided criteria.
     */
    public function search(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            check('keyword', 'string|null');
            check('category_id', 'numeric|null');
            check('is_active', 'bool|int|null');
            check('order_by', 'string|null');
            check('limit', 'numeric|null');
            check('offset', 'numeric|null');

            $keyword = request('keyword', '');
            $category_id = request('category_id');
            $is_active = request('is_active');
            $order_by = request('order_by', 'update_datetime DESC');
            $limit = request('limit', 1000);
            $offset = (int) request('offset', '0');

            $category_id = $category_id !== null && $category_id !== '' ? (int) $category_id : null;
            $is_active = $is_active !== null && $is_active !== '' ? (bool) (int) $is_active : null;

            $packages = $this->packages_model->search(
                $keyword,
                $category_id,
                $is_active,
                $limit,
                $offset,
                $order_by,
            );

            json_response($packages);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Store a new package.
     */
    public function store(): void
    {
        try {
            method('post');

            if (cannot('add', PRIV_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            check('package', 'array');

            $package = request('package');

            $this->packages_model->only($package, $this->allowed_package_fields);

            $this->packages_model->optional($package, $this->optional_package_fields);

            $package_id = $this->packages_model->save($package);

            $package = $this->packages_model->find($package_id);

            json_response([
                'success' => true,
                'id' => $package_id,
                'package' => $package,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Find a package.
     */
    public function find(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            check('package_id', 'numeric');

            $package_id = request('package_id');

            if (empty($package_id) || !filter_var($package_id, FILTER_VALIDATE_INT) || $package_id <= 0) {
                throw new InvalidArgumentException('Invalid package ID provided.');
            }

            $package = $this->packages_model->find($package_id);

            json_response($package);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Update a package.
     */
    public function update(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            check('package', 'array');

            $package = request('package');

            $this->packages_model->only($package, $this->allowed_package_fields);

            $this->packages_model->optional($package, $this->optional_package_fields);

            $package_id = $this->packages_model->save($package);

            $package = $this->packages_model->find($package_id);

            json_response([
                'success' => true,
                'id' => $package_id,
                'package' => $package,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Remove a package.
     */
    public function destroy(): void
    {
        try {
            method('post');

            if (cannot('delete', PRIV_PACKAGES)) {
                abort(403, 'Forbidden');
            }

            check('package_id', 'numeric');

            $package_id = request('package_id');

            if (empty($package_id) || !filter_var($package_id, FILTER_VALIDATE_INT) || $package_id <= 0) {
                throw new InvalidArgumentException('Invalid package ID provided.');
            }

            $this->packages_model->delete($package_id);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
