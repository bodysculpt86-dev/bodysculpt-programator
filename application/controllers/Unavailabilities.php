<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

/**
 * Unavailabilities controller.
 *
 * Handles the unavailabilities related operations.
 *
 * @package Controllers
 */
class Unavailabilities extends EA_Controller
{
    public array $allowed_unavailability_fields = [
        'id',
        'start_datetime',
        'end_datetime',
        'location',
        'notes',
        'is_unavailability',
        'id_users_provider',
    ];

    public array $optional_unavailability_fields = [
        //
    ];

    /**
     * Unavailabilities constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('unavailabilities_model');
        $this->load->model('roles_model');

        $this->load->library('accounts');
        $this->load->library('timezones');
        $this->load->library('webhooks_client');
    }

    /**
     * Filter unavailabilities by the provided keyword.
     */
    public function search(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            check('keyword', 'string|null');
            check('order_by', 'string|null');
            check('limit', 'numeric|null');
            check('offset', 'numeric|null');

            $keyword = request('keyword', '');

            $order_by = request('order_by', 'update_datetime DESC');

            $limit = request('limit', 1000);

            $offset = (int) request('offset', '0');

            $unavailabilities = $this->unavailabilities_model->search($keyword, $limit, $offset, $order_by);

            // All employees can manage unavailabilities across all providers.

            json_response($unavailabilities);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Store a new unavailability.
     */
    public function store(): void
    {
        try {
            method('post');

            if (cannot('add', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            check('unavailability', 'array');

            $unavailability = request('unavailability');

            $this->unavailabilities_model->only($unavailability, $this->allowed_unavailability_fields);

            $this->unavailabilities_model->optional($unavailability, $this->optional_unavailability_fields);

            $unavailability_id = $this->unavailabilities_model->save($unavailability);

            $unavailability = $this->unavailabilities_model->find($unavailability_id);

            $provider = $this->providers_model->find($unavailability['id_users_provider']);

            $this->synchronization->sync_unavailability_saved($unavailability, $provider);

            $this->webhooks_client->trigger(WEBHOOK_UNAVAILABILITY_SAVE, $unavailability);

            json_response([
                'success' => true,
                'id' => $unavailability_id,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Find an unavailability.
     */
    public function find(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            check('unavailability_id', 'numeric');

            $unavailability_id = request('unavailability_id');

            // Validate unavailability_id is a positive integer
            if (
                empty($unavailability_id) ||
                !filter_var($unavailability_id, FILTER_VALIDATE_INT) ||
                $unavailability_id <= 0
            ) {
                throw new InvalidArgumentException('Invalid unavailability ID provided.');
            }

            $this->check_unavailability_access((int) $unavailability_id);

            $unavailability = $this->unavailabilities_model->find($unavailability_id);

            json_response($unavailability);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Update an unavailability.
     */
    public function update(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            check('unavailability', 'array');

            $unavailability = request('unavailability');

            if (!empty($unavailability['id'])) {
                $this->check_unavailability_access((int) $unavailability['id']);
            }

            $this->unavailabilities_model->only($unavailability, $this->allowed_unavailability_fields);

            $this->unavailabilities_model->optional($unavailability, $this->optional_unavailability_fields);

            $unavailability_id = $this->unavailabilities_model->save($unavailability);

            $unavailability = $this->unavailabilities_model->find($unavailability_id);

            $provider = $this->providers_model->find($unavailability['id_users_provider']);

            $this->synchronization->sync_unavailability_saved($unavailability, $provider);

            $this->webhooks_client->trigger(WEBHOOK_UNAVAILABILITY_SAVE, $unavailability);

            json_response([
                'success' => true,
                'id' => $unavailability_id,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Remove an unavailability.
     */
    public function destroy(): void
    {
        try {
            method('post');

            if (cannot('delete', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            check('unavailability_id', 'numeric');

            $unavailability_id = request('unavailability_id');

            // Validate unavailability_id is a positive integer
            if (
                empty($unavailability_id) ||
                !filter_var($unavailability_id, FILTER_VALIDATE_INT) ||
                $unavailability_id <= 0
            ) {
                throw new InvalidArgumentException('Invalid unavailability ID provided.');
            }

            $this->check_unavailability_access((int) $unavailability_id);

            $unavailability = $this->unavailabilities_model->find($unavailability_id);

            $this->unavailabilities_model->delete($unavailability_id);

            $this->webhooks_client->trigger(WEBHOOK_UNAVAILABILITY_DELETE, $unavailability);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Check whether the current user has access to the unavailability's provider.
     */
    private function check_unavailability_access(int $unavailability_id): void
    {
        // All employees can manage unavailabilities across all providers. The
        // add/edit/delete privilege checks are performed separately.
    }
}
