<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Calendar controller.
 *
 * Handles calendar related operations.
 *
 * @package Controllers
 */
class Calendar extends EA_Controller
{
    public array $allowed_customer_fields = [
        'id',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'address',
        'city',
        'state',
        'zip_code',
        'timezone',
        'language',
        'notes',
        'custom_field_1',
        'custom_field_2',
        'custom_field_3',
        'custom_field_4',
        'custom_field_5',
    ];

    public array $optional_customer_fields = [
        //
    ];

    public array $allowed_appointment_fields = [
        'id',
        'start_datetime',
        'end_datetime',
        'location',
        'meeting_link',
        'notes',
        'color',
        'status',
        'is_unavailability',
        'id_users_provider',
        'id_users_customer',
        'id_services',
        'id_customer_packages',
        'price',
    ];

    public array $optional_appointment_fields = [
        'price' => null,
        'id_customer_packages' => null,
    ];

    /**
     * Calendar constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('unavailabilities_model');
        $this->load->model('blocked_periods_model');
        $this->load->model('customers_model');
        $this->load->model('services_model');
        $this->load->model('providers_model');
        $this->load->model('roles_model');

        $this->load->library('accounts');
        $this->load->library('google_sync');
        $this->load->library('notifications');
        $this->load->library('synchronization');
        $this->load->library('timezones');
        $this->load->library('webhooks_client');
        $this->load->library('permissions');
        $this->load->library('jitsi_client');
    }

    /**
     * Render the calendar page and display the selected appointment.
     *
     * This method will call the "index" callback to handle the page rendering.
     *
     * @param string $appointment_hash Appointment hash.
     */
    public function reschedule(string $appointment_hash): void
    {
        $this->index($appointment_hash);
    }

    /**
     * Display the main backend page.
     *
     * This method displays the main backend page. All login permission can view this page which displays a calendar
     * with the events of the selected provider or service. If a user has more privileges he will see more menus at the
     * top of the page.
     *
     * @param string $appointment_hash Appointment hash.
     */
    public function index(string $appointment_hash = ''): void
    {
        method('get');

        session([
            'dest_url' => site_url('calendar/index' . (!empty($appointment_hash) ? '/' . $appointment_hash : '')),
        ]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_APPOINTMENTS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');

        $user = $this->users_model->find($user_id);

        $secretary_providers = [];

        if ($role_slug === DB_SLUG_SECRETARY) {
            $secretary = $this->secretaries_model->find(session('user_id'));

            $secretary_providers = $secretary['providers'];
        }

        $edit_appointment = null;

        if (!empty($appointment_hash)) {
            $occurrences = $this->appointments_model->get(['hash' => $appointment_hash]);

            if ($appointment_hash !== '' && !empty($occurrences)) {
                $edit_appointment = $occurrences[0];

                $this->appointments_model->load($edit_appointment, ['customer']);
            }
        }

        $privileges = $this->roles_model->get_permissions_by_slug($role_slug);

        $available_providers = $this->providers_model->get_available_providers();

        // All roles see all providers in the calendar (clinic-wide view).
        $available_services = $this->services_model->get_available_services();

        // Filter services to only include those that can be served by at least one available provider
        $provider_service_ids = [];
        foreach ($available_providers as $provider) {
            foreach ($provider['services'] as $service_id) {
                $provider_service_ids[$service_id] = true;
            }
        }

        $available_services = array_values(
            array_filter($available_services, function ($service) use ($provider_service_ids) {
                return isset($provider_service_ids[$service['id']]);
            }),
        );

        $calendar_view = request('view', $user['settings']['calendar_view']);

        // Force Table View for all users and all roles.
        $calendar_view = CALENDAR_VIEW_TABLE;

        $appointment_status_options = setting('appointment_status_options');

        $appointment_closing_statuses = setting('appointment_closing_statuses');

        $customers = $this->customers_model->get(null, 50, null, 'update_datetime DESC');

        if (setting('limit_customer_access') && $role_slug === DB_SLUG_PROVIDER) {
            // Only include the customers that the provider is supposed to see (they had past booking together)
            $CI = $this;

            $customers = array_values(
                array_filter($customers, function ($customer) use ($user_id, $CI) {
                    if (!$CI->permissions->has_customer_access($user_id, $customer['id'])) {
                        return false;
                    }

                    return true;
                }),
            );
        }

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
            'first_weekday' => setting('first_weekday'),
            'company_working_plan' => setting('company_working_plan'),
            'timezones' => $this->timezones->to_array(),
            'privileges' => $privileges,
            'calendar_view' => $calendar_view,
            'available_providers' => filter_sensitive_users_data($available_providers),
            'available_services' => $available_services,
            'secretary_providers' => $secretary_providers,
            'edit_appointment' => $edit_appointment,
            'google_sync_feature' => filter_var(
                setting('google_sync_feature') ?: config('google_sync_feature'),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'customers' => $customers,
            'default_language' => setting('default_language'),
            'default_timezone' => setting('default_timezone'),
        ]);

        html_vars([
            'page_title' => lang('calendar'),
            'active_menu' => PRIV_APPOINTMENTS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'timezone' => setting('default_timezone'),
            'timezones' => $this->timezones->to_array(),
            'grouped_timezones' => $this->timezones->to_grouped_array(),
            'privileges' => $privileges,
            'calendar_view' => $calendar_view,
            'available_providers' => filter_sensitive_users_data($available_providers),
            'available_services' => $available_services,
            'secretary_providers' => $secretary_providers,
            'appointment_status_options' => json_decode($appointment_status_options, true) ?? [],
            'appointment_closing_statuses' => json_decode($appointment_closing_statuses, true) ?? [],
            'require_first_name' => setting('require_first_name'),
            'require_last_name' => setting('require_last_name'),
            'require_email' => setting('require_email'),
            'require_phone_number' => setting('require_phone_number'),
            'require_address' => setting('require_address'),
            'require_city' => setting('require_city'),
            'require_zip_code' => setting('require_zip_code'),
            'require_notes' => setting('require_notes'),
        ]);

        $this->load->view('pages/calendar');
    }

    /**
     * Save appointment changes that are made from the backend calendar page.
     *
     * Supports saving a single appointment or multiple appointments at once (e.g. when the customer
     * books more than one service).
     */
    public function save_appointment(): void
    {
        try {
            method('post');

            check('customer_data', 'array|null');
            check('appointment_data', 'array');
            check('notify_users', 'bool|null');
            check('force_save', 'bool|null');

            $customer_data = request('customer_data');

            $appointment_data = request('appointment_data');

            $notify_users = filter_var(request('notify_users', true), FILTER_VALIDATE_BOOLEAN);

            $force_save = filter_var(request('force_save', false), FILTER_VALIDATE_BOOLEAN);

            // Normalize the appointment data to a list of appointment records.
            $appointments = array_is_list($appointment_data) ? array_values($appointment_data) : [$appointment_data];

            if (empty($appointments)) {
                throw new InvalidArgumentException('No appointment data provided.');
            }

            $this->check_event_permissions((int) ($appointments[0]['id_users_provider'] ?? 0));

            // Save customer changes to the database.
            $customer_id = null;

            if ($customer_data) {
                $customer = $customer_data;

                $required_permissions = !empty($customer['id'])
                    ? can('add', PRIV_CUSTOMERS)
                    : can('edit', PRIV_CUSTOMERS);

                if (!$required_permissions) {
                    throw new RuntimeException('You do not have the required permissions for this task.');
                }

                $this->customers_model->only($customer, $this->allowed_customer_fields);

                $this->customers_model->optional($customer, $this->optional_customer_fields);

                // Reuse existing customers by email (same behavior as public booking).
                if (empty($customer['id']) && $this->customers_model->exists($customer)) {
                    $customer['id'] = $this->customers_model->find_record_id($customer);
                }

                $customer['id'] = $this->customers_model->save($customer);

                $customer_id = $customer['id'];
            }

            // Save appointment changes to the database.
            $manage_mode = !empty($appointments[0]['id']);

            foreach ($appointments as &$appointment) {
                $required_permissions = !empty($appointment['id'])
                    ? can('edit', PRIV_APPOINTMENTS)
                    : can('add', PRIV_APPOINTMENTS);

                if (!$required_permissions) {
                    throw new RuntimeException('You do not have the required permissions for this task.');
                }

                // If the appointment does not contain the customer record id, then it means that is going to be inserted.
                if (!isset($appointment['id_users_customer'])) {
                    $appointment['id_users_customer'] = $customer_id ?? $customer_data['id'] ?? null;
                }
            }
            unset($appointment);

            // Check if any of the providers has a conflicting appointment at the selected time.
            foreach ($appointments as $appointment) {
                $exclude_appointment_id = !empty($appointment['id']) ? (int) $appointment['id'] : null;

                $has_conflict = $this->appointments_model->has_provider_conflict(
                    (int) $appointment['id_users_provider'],
                    $appointment['start_datetime'],
                    $appointment['end_datetime'],
                    $exclude_appointment_id,
                );

                if ($has_conflict && !$force_save) {
                    json_response([
                        'success' => false,
                        'conflict' => true,
                        'message' => lang('provider_has_conflicting_appointment'),
                    ]);
                    return;
                }
            }

            // Save all appointments.
            foreach ($appointments as &$appointment) {
                if ($manage_mode && !empty($appointment['id'])) {
                    $this->synchronization->remove_appointment_on_provider_change($appointment['id']);
                }

                // Jitsi integration: if enabled and meeting_link is empty, generate a Jitsi meeting link
                if (setting('jitsi_enabled') === '1' && empty($appointment['meeting_link'])) {
                    $appointment['meeting_link'] = $this->jitsi_client->generate_link();
                }

                $this->appointments_model->only($appointment, $this->allowed_appointment_fields);

                $this->appointments_model->optional($appointment, $this->optional_appointment_fields);

                $appointment['id'] = $this->appointments_model->save($appointment);
            }
            unset($appointment);

            // Sync, notify and trigger webhooks for each saved appointment.
            foreach ($appointments as $appointment) {
                $saved_appointment = $this->appointments_model->find($appointment['id']);
                $provider = $this->providers_model->find($saved_appointment['id_users_provider']);
                $customer = $this->customers_model->find($saved_appointment['id_users_customer']);
                $service = $this->services_model->find($saved_appointment['id_services']);

                $company_color = setting('company_color');

                $settings = [
                    'company_name' => setting('company_name'),
                    'company_link' => setting('company_link'),
                    'company_email' => setting('company_email'),
                    'company_color' =>
                        !empty($company_color) && $company_color != DEFAULT_COMPANY_COLOR ? $company_color : null,
                    'date_format' => setting('date_format'),
                    'time_format' => setting('time_format'),
                ];

                $this->synchronization->sync_appointment_saved(
                    $saved_appointment,
                    $service,
                    $provider,
                    $customer,
                    $settings,
                );

                if ($notify_users) {
                    $this->notifications->notify_appointment_saved(
                        $saved_appointment,
                        $service,
                        $provider,
                        $customer,
                        $settings,
                        $manage_mode,
                    );
                }

                $this->webhooks_client->trigger(WEBHOOK_APPOINTMENT_SAVE, $saved_appointment);
            }

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    private function check_event_permissions(int $provider_id): void
    {
        // All employees (admin/provider/secretary) can manage appointments and
        // unavailabilities for any provider as long as they have the calendar
        // appointments privilege. The privilege check is performed separately.
    }

    /**
     * Delete appointment from the database.
     *
     * This method deletes an existing appointment from the database. Once this action is finished it cannot be undone.
     * Notification emails are send to both provider and customer and the delete action is executed to the Google
     * Calendar account of the provider, if the "google_sync" setting is enabled.
     */
    public function delete_appointment(): void
    {
        try {
            method('post');

            if (cannot('delete', 'appointments')) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('appointment_id', 'numeric');
            check('cancellation_reason', 'string|null');
            check('notify_users', 'bool|null');

            $appointment_id = request('appointment_id');
            $cancellation_reason = (string) request('cancellation_reason');
            $notify_users = filter_var(request('notify_users', true), FILTER_VALIDATE_BOOLEAN);

            if (empty($appointment_id)) {
                throw new InvalidArgumentException('No appointment id provided.');
            }

            // Store appointment data for later use in this method.
            $appointment = $this->appointments_model->find($appointment_id);

            $this->check_event_permissions((int) $appointment['id_users_provider']);

            $provider = $this->providers_model->find($appointment['id_users_provider']);
            $customer = $this->customers_model->find($appointment['id_users_customer']);
            $service = $this->services_model->find($appointment['id_services']);

            $company_color = setting('company_color');

            $settings = [
                'company_name' => setting('company_name'),
                'company_email' => setting('company_email'),
                'company_link' => setting('company_link'),
                'company_color' =>
                    !empty($company_color) && $company_color != DEFAULT_COMPANY_COLOR ? $company_color : null,
                'date_format' => setting('date_format'),
                'time_format' => setting('time_format'),
            ];

            // Delete appointment record from the database.
            $this->appointments_model->delete($appointment_id);

            if ($notify_users) {
                $this->notifications->notify_appointment_deleted(
                    $appointment,
                    $service,
                    $provider,
                    $customer,
                    $settings,
                    $cancellation_reason,
                );
            }

            $this->synchronization->sync_appointment_deleted($appointment, $provider);

            $this->webhooks_client->trigger(WEBHOOK_APPOINTMENT_DELETE, $appointment);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Insert of update unavailability to database.
     */
    public function save_unavailability(): void
    {
        try {
            method('post');

            check('unavailability', 'array');

            // Check privileges
            $unavailability = request('unavailability');

            $required_permissions = !isset($unavailability['id'])
                ? can('add', PRIV_APPOINTMENTS)
                : can('edit', PRIV_APPOINTMENTS);

            if (!$required_permissions) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            $provider_id = (int) $unavailability['id_users_provider'];

            $this->check_event_permissions($provider_id);

            $provider = $this->providers_model->find($provider_id);

            $unavailability_id = $this->unavailabilities_model->save($unavailability);

            $unavailability = $this->unavailabilities_model->find($unavailability_id);

            $this->synchronization->sync_unavailability_saved($unavailability, $provider);

            $this->webhooks_client->trigger(WEBHOOK_UNAVAILABILITY_SAVE, $unavailability);

            json_response([
                'success' => true,
                'warnings' => $warnings ?? [],
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Delete an unavailability from database.
     */
    public function delete_unavailability(): void
    {
        method('post');
        try {
            if (cannot('delete', PRIV_APPOINTMENTS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('unavailability_id', 'numeric');

            $unavailability_id = request('unavailability_id');

            $unavailability = $this->unavailabilities_model->find($unavailability_id);

            $this->check_event_permissions((int) $unavailability['id_users_provider']);

            $provider = $this->providers_model->find($unavailability['id_users_provider']);

            $this->unavailabilities_model->delete($unavailability_id);

            $this->synchronization->sync_unavailability_deleted($unavailability, $provider);

            $this->webhooks_client->trigger(WEBHOOK_UNAVAILABILITY_DELETE, $unavailability);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Insert or update working plan exceptions to database.
     */
    public function save_working_plan_exception(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_USERS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('working_plan_exception', 'array');
            check('provider_id', 'numeric');

            $working_plan_exception = request('working_plan_exception');

            $provider_id = request('provider_id');

            $exception_id = $this->providers_model->save_working_plan_exception($provider_id, $working_plan_exception);

            json_response([
                'success' => true,
                'id' => $exception_id,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Delete a working plan exception from database.
     */
    public function delete_working_plan_exception(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_USERS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('exception_id', 'numeric');
            check('provider_id', 'numeric');

            $exception_id = request('exception_id');

            $provider_id = request('provider_id');

            $this->load->model('working_plan_exceptions_model');
            $this->working_plan_exceptions_model->delete($exception_id);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Get Calendar Events
     *
     * This method will return all the calendar events within a specified period.
     */
    public function get_calendar_appointments_for_table_view(): void
    {
        try {
            method('post');

            $required_permissions = can('view', PRIV_APPOINTMENTS);

            if (!$required_permissions) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('start_date', 'date');
            check('end_date', 'date');

            $start_date = request('start_date') . ' 00:00:00';

            $end_date = request('end_date') . ' 23:59:59';

            $response = [
                'appointments' => $this->appointments_model->get([
                    'start_datetime >=' => $start_date,
                    'end_datetime <=' => $end_date,
                ]),
                'unavailabilities' => $this->unavailabilities_model->get([
                    'start_datetime >=' => $start_date,
                    'end_datetime <=' => $end_date,
                ]),
            ];

            foreach ($response['appointments'] as &$appointment) {
                $appointment['provider'] = $this->providers_model->find($appointment['id_users_provider']);
                $appointment['service'] = $this->services_model->find($appointment['id_services']);
                $appointment['customer'] = $this->customers_model->find($appointment['id_users_customer']);
            }

            unset($appointment);

            // All roles see all appointments/unavailabilities in the calendar.

            foreach ($response['unavailabilities'] as &$unavailability) {
                $unavailability['provider'] = $this->providers_model->find($unavailability['id_users_provider']);
            }

            unset($unavailability);

            // Add blocked periods to the response.
            $start_date = request('start_date');
            $end_date = request('end_date');
            $response['blocked_periods'] = $this->blocked_periods_model->get_for_period($start_date, $end_date);

            json_response($response);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Get the registered appointments for the given date period and record.
     *
     * This method returns the database appointments and unavailability periods for the user selected date period and
     * record type (provider or service).
     */
    public function get_calendar_appointments(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('record_id', 'string|numeric|null');
            check('filter_type', 'string|null');
            check('start_date', 'date');
            check('end_date', 'date');

            $record_id = request('record_id');

            $is_all = request('record_id') === FILTER_TYPE_ALL;

            $filter_type = request('filter_type');

            if (!$filter_type && !$is_all) {
                json_response([
                    'appointments' => [],
                    'unavailabilities' => [],
                ]);

                return;
            }

            // Validate filter_type to prevent SQL injection via column name
            $allowed_filter_types = [FILTER_TYPE_PROVIDER, FILTER_TYPE_SERVICE, FILTER_TYPE_ALL];
            if ($filter_type && !in_array($filter_type, $allowed_filter_types, true)) {
                throw new InvalidArgumentException('Invalid filter type provided.');
            }

            // Determine which column to filter by
            if ($filter_type == FILTER_TYPE_PROVIDER) {
                $where_id = 'id_users_provider';
            } elseif ($filter_type === FILTER_TYPE_SERVICE) {
                $where_id = 'id_services';
            } else {
                $where_id = 'id_users_provider'; // Default for FILTER_TYPE_ALL
            }

            // Validate record_id is numeric when not "all"
            if (!$is_all && !is_numeric($record_id)) {
                throw new InvalidArgumentException('Invalid record ID provided.');
            }

            // Get appointments using query builder for safety
            $start_date = request('start_date');
            $end_date = date('Y-m-d', strtotime(request('end_date') . ' +1 day'));

            // Build query using CodeIgniter's query builder for SQL injection protection
            $this->db->select('*');
            $this->db->from('appointments');

            if (!$is_all) {
                $this->db->where($where_id, $record_id);
            }

            $this->db->group_start();
            $this->db->group_start();
            $this->db->where('start_datetime >', $start_date);
            $this->db->where('start_datetime <', $end_date);
            $this->db->group_end();
            $this->db->or_group_start();
            $this->db->where('end_datetime >', $start_date);
            $this->db->where('end_datetime <', $end_date);
            $this->db->group_end();
            $this->db->or_group_start();
            $this->db->where('start_datetime <=', $start_date);
            $this->db->where('end_datetime >=', $end_date);
            $this->db->group_end();
            $this->db->group_end();

            $this->db->where('is_unavailability', 0);

            $response['appointments'] = $this->db->get()->result_array();

            foreach ($response['appointments'] as &$appointment) {
                $appointment['provider'] = $this->providers_model->find($appointment['id_users_provider']);
                $appointment['service'] = $this->services_model->find($appointment['id_services']);
                $appointment['customer'] = $this->customers_model->find($appointment['id_users_customer']);
            }

            unset($appointment);

            // Get unavailability periods (only for provider).
            $response['unavailabilities'] = [];

            if ($filter_type == FILTER_TYPE_PROVIDER || $is_all) {
                // Build query using CodeIgniter's query builder for SQL injection protection
                $this->db->select('*');
                $this->db->from('appointments');

                if (!$is_all) {
                    $this->db->where($where_id, $record_id);
                }

                $this->db->group_start();
                $this->db->group_start();
                $this->db->where('start_datetime >', $start_date);
                $this->db->where('start_datetime <', $end_date);
                $this->db->group_end();
                $this->db->or_group_start();
                $this->db->where('end_datetime >', $start_date);
                $this->db->where('end_datetime <', $end_date);
                $this->db->group_end();
                $this->db->or_group_start();
                $this->db->where('start_datetime <=', $start_date);
                $this->db->where('end_datetime >=', $end_date);
                $this->db->group_end();
                $this->db->group_end();

                $this->db->where('is_unavailability', 1);

                $response['unavailabilities'] = $this->db->get()->result_array();
            }

            $user_id = session('user_id');

            $role_slug = session('role_slug');

            // All roles see all appointments/unavailabilities in the calendar.

            foreach ($response['unavailabilities'] as &$unavailability) {
                $unavailability['provider'] = $this->providers_model->find($unavailability['id_users_provider']);
            }

            unset($unavailability);

            // Add blocked periods to the response.
            $start_date = request('start_date');
            $end_date = request('end_date');
            $response['blocked_periods'] = $this->blocked_periods_model->get_for_period($start_date, $end_date);

            json_response($response);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
