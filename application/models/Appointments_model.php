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
 * Appointments model.
 *
 * @package Models
 */
class Appointments_model extends EA_Model
{
    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'is_unavailability' => 'boolean',
        'id_users_provider' => 'integer',
        'id_users_customer' => 'integer',
        'id_services' => 'integer',
        'id_customer_packages' => 'integer',
    ];

    /**
     * @var array
     */
    protected array $api_resource = [
        'id' => 'id',
        'book' => 'book_datetime',
        'start' => 'start_datetime',
        'end' => 'end_datetime',
        'location' => 'location',
        'meetingLink' => 'meeting_link',
        'color' => 'color',
        'status' => 'status',
        'price' => 'price',
        'notes' => 'notes',
        'hash' => 'hash',
        'serviceId' => 'id_services',
        'providerId' => 'id_users_provider',
        'customerId' => 'id_users_customer',
        'googleCalendarId' => 'id_google_calendar',
        'caldavCalendarId' => 'id_caldav_calendar',
    ];

    /**
     * Save (insert or update) an appointment.
     *
     * @param array $appointment Associative array with the appointment data.
     *
     * @return int Returns the appointment ID.
     *
     * @throws InvalidArgumentException
     */
    public function save(array $appointment): int
    {
        $this->validate($appointment);

        $old_appointment = null;

        if (!empty($appointment['id'])) {
            $old_appointment = $this->find((int) $appointment['id']);
        }

        $this->db->trans_start();

        try {
            $appointment_id = empty($appointment['id'])
                ? $this->insert($appointment)
                : $this->update($appointment);

            $this->manage_customer_package_usage(
                $appointment_id,
                isset($appointment['id_customer_packages']) ? (int) $appointment['id_customer_packages'] : null,
                (int) $appointment['id_services'],
                $appointment['status'] ?? null,
                $old_appointment['id_customer_packages'] ?? null,
                $old_appointment['id_services'] ?? null,
                $old_appointment['status'] ?? null,
            );

            $this->db->trans_complete();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }

        if ($this->db->trans_status() === false) {
            throw new RuntimeException('The appointment transaction failed.');
        }

        return $appointment_id;
    }

    /**
     * Adjust customer package remaining quantities when an appointment is linked or unlinked.
     *
     * Consumption happens only when the appointment reaches a closing status, otherwise any
     * previously consumed item is released.
     *
     * @param int $appointment_id
     * @param int|null $new_customer_package_id
     * @param int $new_service_id
     * @param string|null $new_status
     * @param int|null $old_customer_package_id
     * @param int|null $old_service_id
     * @param string|null $old_status
     */
    private function manage_customer_package_usage(
        int $appointment_id,
        ?int $new_customer_package_id,
        int $new_service_id,
        ?string $new_status,
        ?int $old_customer_package_id,
        ?int $old_service_id,
        ?string $old_status,
    ): void {
        $this->load->model('customer_packages_model');

        $new_should_consume = $new_customer_package_id && $this->is_closing_status($new_status);
        $old_was_consumed = $old_customer_package_id && $this->is_closing_status($old_status);

        $same_package = $new_customer_package_id === $old_customer_package_id
            && $new_service_id === $old_service_id;

        if ($same_package && $new_should_consume && $old_was_consumed) {
            return;
        }

        if ($old_was_consumed && (!$same_package || !$new_should_consume)) {
            $this->customer_packages_model->release_item($old_customer_package_id, $old_service_id);
        }

        if ($new_should_consume && (!$same_package || !$old_was_consumed)) {
            $consumed = $this->customer_packages_model->consume_item($new_customer_package_id, $new_service_id);

            if (!$consumed) {
                throw new RuntimeException(
                    'Could not consume customer package item for appointment ' . $appointment_id,
                );
            }
        }
    }

    /**
     * Check whether the given appointment status is a closing/finalization status.
     *
     * @param string|null $status
     *
     * @return bool
     */
    private function is_closing_status(?string $status): bool
    {
        if (!$status) {
            return false;
        }

        $closing_statuses = json_decode(setting('appointment_closing_statuses') ?? '[]', true) ?? [];

        return in_array($status, $closing_statuses, true);
    }

    /**
     * Validate the appointment data.
     *
     * @param array $appointment Associative array with the appointment data.
     *
     * @throws InvalidArgumentException
     */
    public function validate(array $appointment): void
    {
        // If an appointment ID is provided then check whether the record really exists in the database.
        if (!empty($appointment['id'])) {
            $count = $this->db->get_where('appointments', ['id' => $appointment['id']])->num_rows();

            if (!$count) {
                throw new InvalidArgumentException(
                    'The provided appointment ID does not exist in the database: ' . $appointment['id'],
                );
            }
        }

        // Make sure all required fields are provided.

        $require_notes = filter_var(setting('require_notes'), FILTER_VALIDATE_BOOLEAN);

        if (
            empty($appointment['start_datetime']) ||
            empty($appointment['end_datetime']) ||
            empty($appointment['id_services']) ||
            empty($appointment['id_users_provider']) ||
            empty($appointment['id_users_customer']) ||
            (empty($appointment['notes']) && $require_notes)
        ) {
            throw new InvalidArgumentException('Not all required fields are provided: ' . print_r($appointment, true));
        }

        // Make sure that the provided appointment date time values are valid.
        if (!validate_datetime($appointment['start_datetime'])) {
            throw new InvalidArgumentException('The appointment start date time is invalid.');
        }

        if (!validate_datetime($appointment['end_datetime'])) {
            throw new InvalidArgumentException('The appointment end date time is invalid.');
        }

        // Make the appointment lasts longer than the minimum duration (in minutes).
        $diff = (strtotime($appointment['end_datetime']) - strtotime($appointment['start_datetime'])) / 60;

        if ($diff < EVENT_MINIMUM_DURATION) {
            throw new InvalidArgumentException(
                'The appointment duration cannot be less than ' . EVENT_MINIMUM_DURATION . ' minutes.',
            );
        }

        // Make sure the provider ID really exists in the database.
        $count = $this->db
            ->select()
            ->from('users')
            ->join('roles', 'roles.id = users.id_roles', 'inner')
            ->where('users.id', $appointment['id_users_provider'])
            ->where('roles.slug', DB_SLUG_PROVIDER)
            ->get()
            ->num_rows();

        if (!$count) {
            throw new InvalidArgumentException(
                'The appointment provider ID was not found in the database: ' . $appointment['id_users_provider'],
            );
        }

        if (!filter_var($appointment['is_unavailability'], FILTER_VALIDATE_BOOLEAN)) {
            // Make sure the customer ID really exists in the database.
            $count = $this->db
                ->select()
                ->from('users')
                ->join('roles', 'roles.id = users.id_roles', 'inner')
                ->where('users.id', $appointment['id_users_customer'])
                ->where('roles.slug', DB_SLUG_CUSTOMER)
                ->get()
                ->num_rows();

            if (!$count) {
                throw new InvalidArgumentException(
                    'The appointment customer ID was not found in the database: ' . $appointment['id_users_customer'],
                );
            }

            // Make sure the service ID really exists in the database.
            $count = $this->db->get_where('services', ['id' => $appointment['id_services']])->num_rows();

            if (!$count) {
                throw new InvalidArgumentException('Appointment service id is invalid.');
            }

            // Make sure the selected customer package is valid for this appointment.
            if (!empty($appointment['id_customer_packages'])) {
                $this->load->model('customer_packages_model');

                $customer_package = $this->customer_packages_model->find((int) $appointment['id_customer_packages']);

                if ((int) $customer_package['id_users_customer'] !== (int) $appointment['id_users_customer']) {
                    throw new InvalidArgumentException(
                        'The selected customer package does not belong to the appointment customer.',
                    );
                }

                // If the appointment already consumed this exact package/service, we must allow the update even
                // when the package is now inactive or depleted (e.g. the user is moving it back to a non-closing
                // status, which will release the item).
                $is_existing_usage = false;

                if (!empty($appointment['id'])) {
                    $old_appointment = $this->find((int) $appointment['id']);

                    $is_existing_usage =
                        (int) $old_appointment['id_customer_packages'] === (int) $appointment['id_customer_packages']
                        && (int) $old_appointment['id_services'] === (int) $appointment['id_services'];
                }

                if (!$is_existing_usage) {
                    if (empty($customer_package['is_active'])) {
                        throw new InvalidArgumentException('The selected customer package is not active.');
                    }

                    $has_remaining = false;

                    foreach ($customer_package['items'] as $item) {
                        if (
                            (int) $item['id_services'] === (int) $appointment['id_services'] &&
                            (int) $item['quantity_remaining'] > 0
                        ) {
                            $has_remaining = true;
                            break;
                        }
                    }

                    if (!$has_remaining) {
                        throw new InvalidArgumentException(
                            'The selected customer package has no remaining uses for this service.',
                        );
                    }
                }
            }
        }
    }

    /**
     * Get all appointments that match the provided criteria.
     *
     * @param array|string|null $where Where conditions.
     * @param int|null $limit Record limit.
     * @param int|null $offset Record offset.
     * @param string|null $order_by Order by.
     *
     * @return array Returns an array of appointments.
     */
    public function get(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $order_by = null,
    ): array {
        if ($where !== null) {
            $this->db->where($where);
        }

        if ($order_by) {
            $this->db->order_by($this->quote_order_by($order_by));
        }

        $appointments = $this->db
            ->get_where('appointments', ['is_unavailability' => false], $limit, $offset)
            ->result_array();

        foreach ($appointments as &$appointment) {
            $this->cast($appointment);
        }

        return $appointments;
    }

    /**
     * Insert a new appointment into the database.
     *
     * @param array $appointment Associative array with the appointment data.
     *
     * @return int Returns the appointment ID.
     *
     * @throws RuntimeException
     */
    protected function insert(array $appointment): int
    {
        $appointment['book_datetime'] = date('Y-m-d H:i:s');
        $appointment['create_datetime'] = date('Y-m-d H:i:s');
        $appointment['update_datetime'] = date('Y-m-d H:i:s');
        $appointment['hash'] = random_string('alnum', 12);

        do {
            $appointment['confirmation_token'] = $this->generate_confirmation_token();
            $existing = $this->db->get_where('appointments', [
                'confirmation_token' => $appointment['confirmation_token'],
            ])->row_array();
        } while ($existing !== null);

        if (!$this->db->insert('appointments', $appointment)) {
            throw new RuntimeException('Could not insert appointment.');
        }

        return $this->db->insert_id();
    }

    /**
     * Update an existing appointment.
     *
     * @param array $appointment Associative array with the appointment data.
     *
     * @return int Returns the appointment ID.
     *
     * @throws RuntimeException
     */
    protected function update(array $appointment): int
    {
        $appointment['update_datetime'] = date('Y-m-d H:i:s');

        // If the appointment start time changed, reset the SMS reminder flag so the
        // customer receives the reminder for the new date/time instead of the old one.
        if (array_key_exists('start_datetime', $appointment)) {
            $existing = $this->db->get_where('appointments', ['id' => $appointment['id']])->row_array();

            if (!empty($existing) && $existing['start_datetime'] !== $appointment['start_datetime']) {
                $appointment['sms_reminder_sent_at'] = null;
                $appointment['sms_reminder_error'] = null;
                $appointment['reminder_sent_at'] = null;
            }
        }

        if (!$this->db->update('appointments', $appointment, ['id' => $appointment['id']])) {
            throw new RuntimeException('Could not update appointment record.');
        }

        return $appointment['id'];
    }

    /**
     * Get appointments whose start_datetime falls inside the requested window and
     * whose daily reminder has not been sent yet.
     *
     * @param string $from Start of the target window (Y-m-d H:i:s).
     * @param string $until End of the target window (Y-m-d H:i:s).
     * @param array $exclude_statuses Appointment statuses to skip.
     *
     * @return array
     */
    public function get_pending_sms_reminders(string $from, string $until, array $exclude_statuses = []): array
    {
        $this->db
            ->from('appointments')
            ->where('is_unavailability', false)
            ->where('start_datetime >=', $from)
            ->where('start_datetime <=', $until)
            ->where('reminder_sent_at IS NULL', null, false);

        if (!empty($exclude_statuses)) {
            $this->db->where_not_in('status', $exclude_statuses);
        }

        $appointments = $this->db->get()->result_array();

        foreach ($appointments as &$appointment) {
            $this->cast($appointment);
        }

        return $appointments;
    }

    /**
     * Get appointments whose deposit payment link was sent more than 24 hours ago,
     * is still unpaid and has not triggered a manager alert yet.
     *
     * Past appointments and excluded (cancelled/draft/no-show) statuses are skipped.
     *
     * @param string $threshold Datetime (Y-m-d H:i:s); links sent before this count as overdue.
     * @param string $now Datetime (Y-m-d H:i:s); appointments starting before this are skipped.
     * @param array $exclude_statuses Statuses to exclude (cancelled, draft, no-show, ...).
     *
     * @return array
     */
    public function get_pending_unpaid_deposit_alerts(string $threshold, string $now, array $exclude_statuses = []): array
    {
        $this->db
            ->from('appointments')
            ->where('is_unavailability', false)
            ->where('deposit_status', 'unpaid')
            ->where('payment_link_sent_at IS NOT NULL', null, false)
            ->where('payment_link_sent_at <', $threshold)
            ->where('deposit_unpaid_alerted_at IS NULL', null, false)
            ->where('start_datetime >=', $now);

        if (!empty($exclude_statuses)) {
            $this->db->where_not_in('status', $exclude_statuses);
        }

        $appointments = $this->db->get()->result_array();

        foreach ($appointments as &$appointment) {
            $this->cast($appointment);
        }

        return $appointments;
    }

    /**
     * Get a specific appointment from the database.
     *
     * @param int $appointment_id The ID of the record to be returned.
     *
     * @return array Returns an array with the appointment data.
     *
     * @throws InvalidArgumentException
     */
    public function find(int $appointment_id): array
    {
        $appointment = $this->db->get_where('appointments', ['id' => $appointment_id])->row_array();

        if (!$appointment) {
            throw new InvalidArgumentException(
                'The provided appointment ID was not found in the database: ' . $appointment_id,
            );
        }

        $this->cast($appointment);

        return $appointment;
    }

    /**
     * Get a specific field value from the database.
     *
     * @param int $appointment_id Appointment ID.
     * @param string $field Name of the value to be returned.
     *
     * @return mixed Returns the selected appointment value from the database.
     *
     * @throws InvalidArgumentException
     */
    public function value(int $appointment_id, string $field): mixed
    {
        if (empty($field)) {
            throw new InvalidArgumentException('The field argument is cannot be empty.');
        }

        if (empty($appointment_id)) {
            throw new InvalidArgumentException('The appointment ID argument cannot be empty.');
        }

        // Check whether the appointment exists.
        $query = $this->db->get_where('appointments', ['id' => $appointment_id]);

        if (!$query->num_rows()) {
            throw new InvalidArgumentException(
                'The provided appointment ID was not found in the database: ' . $appointment_id,
            );
        }

        // Check if the required field is part of the appointment data.
        $appointment = $query->row_array();

        $this->cast($appointment);

        if (!array_key_exists($field, $appointment)) {
            throw new InvalidArgumentException('The requested field was not found in the appointment data: ' . $field);
        }

        return $appointment[$field];
    }

    /**
     * Remove all the Google Calendar event IDs from appointment records.
     *
     * @param int $provider_id Matching provider ID.
     */
    public function clear_google_sync_ids(int $provider_id): void
    {
        $this->db->update('appointments', ['id_google_calendar' => null], ['id_users_provider' => $provider_id]);
    }

    /**
     * Remove all the Google Calendar event IDs from appointment records.
     *
     * @param int $provider_id Matching provider ID.
     */
    public function clear_caldav_sync_ids(int $provider_id): void
    {
        $this->db->update('appointments', ['id_caldav_calendar' => null], ['id_users_provider' => $provider_id]);
    }

    /**
     * Deletes recurring CalDAV events for the provided date period.
     *
     * @param string $start_date_time
     * @param string $end_date_time
     *
     * @return void
     */
    public function delete_caldav_recurring_events(string $start_date_time, string $end_date_time): void
    {
        $this->db
            ->where('start_datetime >=', $start_date_time)
            ->where('end_datetime <=', $end_date_time)
            ->where('is_unavailability', true)
            ->like('id_caldav_calendar', 'RECURRENCE')
            ->delete('appointments');
    }

    /**
     * Remove an existing appointment from the database.
     *
     * @param int $appointment_id Appointment ID.
     *
     * @throws RuntimeException
     */
    public function delete(int $appointment_id): void
    {
        $appointment = $this->find($appointment_id);

        $this->db->trans_start();

        try {
            if (
                $appointment
                && !empty($appointment['id_customer_packages'])
                && !empty($appointment['id_services'])
                && $this->is_closing_status($appointment['status'])
            ) {
                $this->load->model('customer_packages_model');
                $this->customer_packages_model->release_item(
                    (int) $appointment['id_customer_packages'],
                    (int) $appointment['id_services'],
                );
            }

            $this->db->delete('appointments', ['id' => $appointment_id]);

            $this->db->trans_complete();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }

        if ($this->db->trans_status() === false) {
            throw new RuntimeException('The appointment deletion transaction failed.');
        }
    }

    /**
     * Get the attendants number for the requested period.
     *
     * @param DateTime $start Period start.
     * @param DateTime $end Period end.
     * @param int $service_id Service ID.
     * @param int $provider_id Provider ID.
     * @param int|null $exclude_appointment_id Exclude an appointment from the result set.
     *
     * @return int Returns the number of appointments that match the provided criteria.
     */
    public function get_attendants_number_for_period(
        DateTime $start,
        DateTime $end,
        int $service_id,
        int $provider_id,
        ?int $exclude_appointment_id = null,
    ): int {
        if ($exclude_appointment_id) {
            $this->db->where('id !=', $exclude_appointment_id);
        }

        $this->db
            ->select('count(*) AS attendants_number')
            ->from('appointments')
            ->group_start()
            ->group_start()
            ->where('start_datetime <=', $start->format('Y-m-d H:i:s'))
            ->where('end_datetime >', $start->format('Y-m-d H:i:s'))
            ->group_end()
            ->or_group_start()
            ->where('start_datetime <', $end->format('Y-m-d H:i:s'))
            ->where('end_datetime >=', $end->format('Y-m-d H:i:s'))
            ->group_end()
            ->group_end()
            ->where('id_services', $service_id)
            ->where('id_users_provider', $provider_id);

        if (!empty(APPOINTMENT_NON_BLOCKING_STATUSES)) {
            $this->db->where_not_in('status', APPOINTMENT_NON_BLOCKING_STATUSES);
        }

        $result = $this->db->get()->row_array();

        return $result['attendants_number'];
    }

    /**
     *
     * Returns the number of the other service attendants number for the provided time slot.
     *
     * @param DateTime $start Period start.
     * @param DateTime $end Period end.
     * @param int $service_id Service ID.
     * @param int $provider_id Provider ID.
     * @param int|null $exclude_appointment_id Exclude an appointment from the result set.
     *
     * @return int Returns the number of appointments that match the provided criteria.
     */
    public function get_other_service_attendants_number(
        DateTime $start,
        DateTime $end,
        int $service_id,
        int $provider_id,
        ?int $exclude_appointment_id = null,
    ): int {
        if ($exclude_appointment_id) {
            $this->db->where('id !=', $exclude_appointment_id);
        }

        $this->db
            ->select('count(*) AS attendants_number')
            ->from('appointments')
            ->group_start()
            ->group_start()
            ->where('start_datetime <=', $start->format('Y-m-d H:i:s'))
            ->where('end_datetime >', $start->format('Y-m-d H:i:s'))
            ->group_end()
            ->or_group_start()
            ->where('start_datetime <', $end->format('Y-m-d H:i:s'))
            ->where('end_datetime >=', $end->format('Y-m-d H:i:s'))
            ->group_end()
            ->group_end()
            ->where('id_services !=', $service_id)
            ->where('id_users_provider', $provider_id);

        if (!empty(APPOINTMENT_NON_BLOCKING_STATUSES)) {
            $this->db->where_not_in('status', APPOINTMENT_NON_BLOCKING_STATUSES);
        }

        $result = $this->db->get()->row_array();

        return $result['attendants_number'];
    }

    /**
     * Get the query builder interface, configured for use with the appointments table.
     *
     * @return CI_DB_query_builder
     */
    public function query(): CI_DB_query_builder
    {
        return $this->db->from('appointments');
    }

    /**
     * Search appointments by the provided keyword.
     *
     * @param string $keyword Search keyword.
     * @param int|null $limit Record limit.
     * @param int|null $offset Record offset.
     * @param string|null $order_by Order by.
     *
     * @return array Returns an array of appointments.
     */
    public function search(string $keyword, ?int $limit = null, ?int $offset = null, ?string $order_by = null): array
    {
        $appointments = $this->db
            ->select('appointments.*')
            ->from('appointments')
            ->join('services', 'services.id = appointments.id_services', 'left')
            ->join('users AS providers', 'providers.id = appointments.id_users_provider', 'inner')
            ->join('users AS customers', 'customers.id = appointments.id_users_customer', 'left')
            ->where('is_unavailability', false)
            ->group_start()
            ->like('appointments.start_datetime', $keyword)
            ->or_like('appointments.end_datetime', $keyword)
            ->or_like('appointments.location', $keyword)
            ->or_like('appointments.hash', $keyword)
            ->or_like('appointments.notes', $keyword)
            ->or_like('services.name', $keyword)
            ->or_like('services.description', $keyword)
            ->or_like('providers.first_name', $keyword)
            ->or_like('providers.last_name', $keyword)
            ->or_like('providers.email', $keyword)
            ->or_like('providers.phone_number', $keyword)
            ->or_like('customers.first_name', $keyword)
            ->or_like('customers.last_name', $keyword)
            ->or_like('customers.email', $keyword)
            ->or_like('customers.phone_number', $keyword)
            ->group_end()
            ->limit($limit)
            ->offset($offset)
            ->order_by($this->quote_order_by($order_by))
            ->get()
            ->result_array();

        foreach ($appointments as &$appointment) {
            $this->cast($appointment);
        }

        return $appointments;
    }

    /**
     * Get appointments as options for dropdowns.
     *
     * @param array|string|null $where Where conditions.
     *
     * @return array Returns an array of options with 'value' and 'label' keys.
     */
    public function to_options(array|string|null $where = null): array
    {
        if ($where !== null) {
            $this->db->where($where);
        }

        $appointments = $this->db
            ->select('appointments.id, appointments.start_datetime, services.name AS service_name')
            ->from('appointments')
            ->join('services', 'services.id = appointments.id_services', 'left')
            ->where('is_unavailability', false)
            ->order_by('start_datetime', 'DESC')
            ->get()
            ->result_array();

        $options = [];

        foreach ($appointments as $appointment) {
            $options[] = [
                'value' => (int) $appointment['id'],
                'label' => $appointment['start_datetime'] . ' - ' . ($appointment['service_name'] ?? 'N/A'),
            ];
        }

        return $options;
    }

    /**
     * Load related resources to an appointment.
     *
     * @param array $appointment Associative array with the appointment data.
     * @param array $resources Resource names to be attached ("service", "provider", "customer" supported).
     *
     * @throws InvalidArgumentException
     */
    public function load(array &$appointment, array $resources): void
    {
        if (empty($appointment) || empty($resources)) {
            return;
        }

        foreach ($resources as $resource) {
            switch ($resource) {
                case 'service':
                    $appointment['service'] = $this->db
                        ->get_where('services', [
                            'id' => $appointment['id_services'] ?? ($appointment['serviceId'] ?? null),
                        ])
                        ->row_array();
                    break;

                case 'provider':
                    $appointment['provider'] = $this->db
                        ->get_where('users', [
                            'id' => $appointment['id_users_provider'] ?? ($appointment['providerId'] ?? null),
                        ])
                        ->row_array();
                    break;

                case 'customer':
                    $appointment['customer'] = $this->db
                        ->get_where('users', [
                            'id' => $appointment['id_users_customer'] ?? ($appointment['customerId'] ?? null),
                        ])
                        ->row_array();
                    break;

                default:
                    throw new InvalidArgumentException(
                        'The requested appointment relation is not supported: ' . $resource,
                    );
            }
        }
    }

    /**
     * Convert the database appointment record to the equivalent API resource.
     *
     * @param array $appointment Appointment data.
     */
    public function api_encode(array &$appointment): void
    {
        $encoded_resource = [
            'id' => array_key_exists('id', $appointment) ? (int) $appointment['id'] : null,
            'book' => $appointment['book_datetime'],
            'start' => $appointment['start_datetime'],
            'end' => $appointment['end_datetime'],
            'hash' => $appointment['hash'],
            'color' => $appointment['color'],
            'status' => $appointment['status'],
            'location' => $appointment['location'],
            'notes' => $appointment['notes'],
            'customerId' => $appointment['id_users_customer'] !== null ? (int) $appointment['id_users_customer'] : null,
            'providerId' => $appointment['id_users_provider'] !== null ? (int) $appointment['id_users_provider'] : null,
            'serviceId' => $appointment['id_services'] !== null ? (int) $appointment['id_services'] : null,
            'meetingLink' => $appointment['meeting_link'],
            'googleCalendarId' =>
                $appointment['id_google_calendar'] !== null ? $appointment['id_google_calendar'] : null,
            'caldavCalendarId' =>
                $appointment['id_caldav_calendar'] !== null ? $appointment['id_caldav_calendar'] : null,
        ];

        $appointment = $encoded_resource;
    }

    /**
     * Convert the API resource to the equivalent database appointment record.
     *
     * @param array $appointment API resource.
     * @param array|null $base Base appointment data to be overwritten with the provided values (useful for updates).
     */
    public function api_decode(array &$appointment, ?array $base = null): void
    {
        $decoded_resource = $base ?: [];

        if (array_key_exists('id', $appointment)) {
            $decoded_resource['id'] = $appointment['id'];
        }

        if (array_key_exists('book', $appointment)) {
            $decoded_resource['book_datetime'] = $appointment['book'];
        }

        if (array_key_exists('start', $appointment)) {
            $decoded_resource['start_datetime'] = $appointment['start'];
        }

        if (array_key_exists('end', $appointment)) {
            $decoded_resource['end_datetime'] = $appointment['end'];
        }

        if (array_key_exists('hash', $appointment)) {
            $decoded_resource['hash'] = $appointment['hash'];
        }

        if (array_key_exists('color', $appointment)) {
            $decoded_resource['color'] = $appointment['color'];
        }

        if (array_key_exists('location', $appointment)) {
            $decoded_resource['location'] = $appointment['location'];
        }

        if (array_key_exists('status', $appointment)) {
            $decoded_resource['status'] = $appointment['status'];
        }

        if (array_key_exists('notes', $appointment)) {
            $decoded_resource['notes'] = $appointment['notes'];
        }

        if (array_key_exists('customerId', $appointment)) {
            $decoded_resource['id_users_customer'] = $appointment['customerId'];
        }

        if (array_key_exists('providerId', $appointment)) {
            $decoded_resource['id_users_provider'] = $appointment['providerId'];
        }

        if (array_key_exists('serviceId', $appointment)) {
            $decoded_resource['id_services'] = $appointment['serviceId'];
        }

        if (array_key_exists('googleCalendarId', $appointment)) {
            $decoded_resource['id_google_calendar'] = $appointment['googleCalendarId'];
        }

        if (array_key_exists('caldavCalendarId', $appointment)) {
            $decoded_resource['id_caldav_calendar'] = $appointment['caldavCalendarId'];
        }

        if (array_key_exists('meetingLink', $appointment)) {
            $decoded_resource['meeting_link'] = $appointment['meetingLink'];
        }

        $decoded_resource['is_unavailability'] = false;

        $appointment = $decoded_resource;
    }

    /**
     * Generate a new URL-safe confirmation token.
     *
     * @return string
     */
    public function generate_confirmation_token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    /**
     * Generate a unique confirmation token for an existing appointment and persist it.
     *
     * @param int $appointment_id
     *
     * @return string
     */
    public function regenerate_confirmation_token(int $appointment_id): string
    {
        do {
            $token = $this->generate_confirmation_token();
            $existing = $this->db->get_where('appointments', [
                'confirmation_token' => $token,
            ])->row_array();
        } while ($existing !== null);

        $this->db->update('appointments', ['confirmation_token' => $token], ['id' => $appointment_id]);

        return $token;
    }

    /**
     * Calculate the end date time of an appointment based on the selected service.
     *
     * @param array $appointment Appointment data.
     *
     * @return string Returns the end date time value.
     *
     * @throws Exception
     */
    public function calculate_end_datetime(array $appointment): string
    {
        $duration = $this->db->get_where('services', ['id' => $appointment['id_services']])?->row()?->duration;

        $end_date_time_object = new DateTime($appointment['start_datetime']);

        $end_date_time_object->add(new DateInterval('PT' . $duration . 'M'));

        return $end_date_time_object->format('Y-m-d H:i:s');
    }

    /**
     * Check if the provider has a conflicting appointment at the given time period.
     *
     * @param int $provider_id Provider ID.
     * @param string $start_datetime Start date time of the appointment.
     * @param string $end_datetime End date time of the appointment.
     * @param int|null $exclude_appointment_id Exclude an appointment from the conflict check (useful for updates).
     *
     * @return bool Returns true if there is a conflict, false otherwise.
     */
    public function has_provider_conflict(
        int $provider_id,
        string $start_datetime,
        string $end_datetime,
        ?int $exclude_appointment_id = null,
    ): bool {
        $this->db->select('id')->from('appointments')->where('id_users_provider', $provider_id);

        if ($exclude_appointment_id) {
            $this->db->where('id !=', $exclude_appointment_id);
        }

        if (!empty(APPOINTMENT_NON_BLOCKING_STATUSES)) {
            $this->db->where_not_in('status', APPOINTMENT_NON_BLOCKING_STATUSES);
        }

        // Check for overlapping appointments:
        // An overlap occurs when:  (existing_start < new_end) AND (existing_end > new_start)

        return $this->db
            ->group_start()
            ->where('start_datetime <', $end_datetime)
            ->where('end_datetime >', $start_datetime)
            ->group_end()
            ->get()
            ->num_rows() > 0;
    }

    /**
     * Get daily revenue grouped by date and payment status.
     *
     * Only appointments whose status is in the configured closing statuses (excluding
     * "Nu s-a prezentat") and that are not unavailability entries are counted.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date End date (Y-m-d).
     *
     * @return array List of daily revenue entries.
     */
    public function get_daily_revenue(string $start_date, string $end_date, ?int $provider_id = null): array
    {
        $closing_statuses = json_decode(setting('appointment_closing_statuses'), true) ?? [];

        $revenue_statuses = array_values(array_diff($closing_statuses, ['Nu s-a prezentat']));

        // TODO 2C: 'Consum abonament' NU trebuie contat ca venit — banii se încasează la
        // vânzarea abonamentului, nu la consum. Altfel se dublează veniturile.

        if (empty($revenue_statuses)) {
            return [];
        }

        $this->db
            ->select("DATE(start_datetime) as date, status, SUM(price) as total", false)
            ->from('appointments')
            ->where('is_unavailability', 0);

        if ($provider_id !== null) {
            $this->db->where('id_users_provider', $provider_id);
        }

        $rows = $this->db
            ->where_in('status', $revenue_statuses)
            ->where('start_datetime >=', $start_date . ' 00:00:00')
            ->where('start_datetime <=', $end_date . ' 23:59:59')
            ->group_by('DATE(start_datetime), status')
            ->order_by('date', 'ASC')
            ->get()
            ->result_array();

        $result = [];

        foreach ($rows as $row) {
            $date = $row['date'];

            if (!isset($result[$date])) {
                $result[$date] = [
                    'date' => $date,
                    'total' => 0.0,
                    'statuses' => [],
                ];
            }

            $amount = (float) $row['total'];

            $result[$date]['total'] += $amount;
            $result[$date]['statuses'][$row['status']] = $amount;
        }

        return array_values($result);
    }

    /**
     * Get monthly revenue grouped by month and payment status.
     *
     * Only appointments whose status is in the configured closing statuses (excluding
     * "Nu s-a prezentat") and that are not unavailability entries are counted.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date End date (Y-m-d).
     *
     * @return array List of monthly revenue entries.
     */
    public function get_monthly_revenue(string $start_date, string $end_date, ?int $provider_id = null): array
    {
        $closing_statuses = json_decode(setting('appointment_closing_statuses'), true) ?? [];

        $revenue_statuses = array_values(array_diff($closing_statuses, ['Nu s-a prezentat']));

        if (empty($revenue_statuses)) {
            return [];
            }

        $this->db
            ->select("DATE_FORMAT(start_datetime, '%Y-%m') as month, status, SUM(price) as total", false)
            ->from('appointments')
            ->where('is_unavailability', 0);

        if ($provider_id !== null) {
            $this->db->where('id_users_provider', $provider_id);
        }

        $rows = $this->db
            ->where_in('status', $revenue_statuses)
            ->where('start_datetime >=', $start_date . ' 00:00:00')
            ->where('start_datetime <=', $end_date . ' 23:59:59')
            ->group_by("DATE_FORMAT(start_datetime, '%Y-%m'), status")
            ->order_by('month', 'ASC')
            ->get()
            ->result_array();

        $result = [];

        foreach ($rows as $row) {
            $month = $row['month'];

            if (!isset($result[$month])) {
                $result[$month] = [
                    'month' => $month,
                    'total' => 0.0,
                    'statuses' => [],
                ];
            }

            $amount = (float) $row['total'];

            $result[$month]['total'] += $amount;
            $result[$month]['statuses'][$row['status']] = $amount;
        }

        return array_values($result);
    }

    /**
     * Get an activity matrix grouped by month/category/service and provider.
     *
     * Only appointments whose status is in the configured closing statuses (excluding
     * "Nu s-a prezentat") and that are not unavailability entries are counted.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date End date (Y-m-d).
     * @param array|null $payment_statuses Optional payment status filter.
     * @param string $group_by Dimension to group rows by: 'month', 'category' or 'service'.
     *
     * @return array Activity matrix with periods, provider totals and grand total.
     */
    public function get_activity_matrix(string $start_date, string $end_date, ?array $payment_statuses = null, string $group_by = 'month'): array
    {
        $allowed = ['month', 'category', 'service'];

        if (!in_array($group_by, $allowed, true)) {
            $group_by = 'month';
        }

        $closing_statuses = json_decode(setting('appointment_closing_statuses'), true) ?? [];

        $revenue_statuses = array_values(array_diff($closing_statuses, ['Nu s-a prezentat']));

        if (!empty($payment_statuses)) {
            $revenue_statuses = array_values(array_intersect($revenue_statuses, $payment_statuses));
        }

        if (empty($revenue_statuses)) {
            return [
                'periods' => [],
                'provider_totals' => [],
                'grand_total' => ['cnt' => 0, 'total' => 0.0],
            ];
        }

        $this->db
            ->from('appointments a')
            ->where('a.is_unavailability', 0)
            ->where_in('a.status', $revenue_statuses)
            ->where('a.start_datetime >=', $start_date . ' 00:00:00')
            ->where('a.start_datetime <=', $end_date . ' 23:59:59');

        if ($group_by === 'category') {
            $uncategorizedLabel = addslashes(lang('uncategorized'));

            $this->db
                ->select("COALESCE(sc.name, '{$uncategorizedLabel}') AS period, a.id_users_provider, COUNT(*) AS cnt, SUM(a.price) AS total", false)
                ->join('services s', 's.id = a.id_services', 'left')
                ->join('service_categories sc', 'sc.id = s.id_service_categories', 'left')
                ->group_by("COALESCE(sc.name, '{$uncategorizedLabel}'), a.id_users_provider");
        } elseif ($group_by === 'service') {
            $this->db
                ->select("s.name AS period, a.id_users_provider, COUNT(*) AS cnt, SUM(a.price) AS total", false)
                ->join('services s', 's.id = a.id_services', 'left')
                ->group_by("s.name, a.id_users_provider");
        } else {
            $this->db
                ->select("DATE_FORMAT(a.start_datetime, '%Y-%m') AS period, a.id_users_provider, COUNT(*) AS cnt, SUM(a.price) AS total", false)
                ->group_by("DATE_FORMAT(a.start_datetime, '%Y-%m'), a.id_users_provider");
        }

        $rows = $this->db
            ->order_by('period', 'ASC')
            ->get()
            ->result_array();

        $periods = [];
        $provider_totals = [];
        $grand_total = ['cnt' => 0, 'total' => 0.0];

        foreach ($rows as $row) {
            $period = $row['period'];
            $provider_id = (int) $row['id_users_provider'];
            $cnt = (int) $row['cnt'];
            $total = (float) $row['total'];

            if (!isset($periods[$period])) {
                $periods[$period] = [
                    'period' => $period,
                    'cells' => [],
                    'row_count' => 0,
                    'row_total' => 0.0,
                ];
            }

            $periods[$period]['cells'][$provider_id] = [
                'cnt' => $cnt,
                'total' => $total,
            ];
            $periods[$period]['row_count'] += $cnt;
            $periods[$period]['row_total'] += $total;

            if (!isset($provider_totals[$provider_id])) {
                $provider_totals[$provider_id] = ['cnt' => 0, 'total' => 0.0];
            }

            $provider_totals[$provider_id]['cnt'] += $cnt;
            $provider_totals[$provider_id]['total'] += $total;

            $grand_total['cnt'] += $cnt;
            $grand_total['total'] += $total;
        }

        return [
            'periods' => array_values($periods),
            'provider_totals' => $provider_totals,
            'grand_total' => $grand_total,
        ];
    }
}
