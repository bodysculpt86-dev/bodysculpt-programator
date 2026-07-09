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
 * Customer packages model.
 *
 * Handles the database operations of sold subscriptions/packages.
 *
 * @package Models
 */
class Customer_packages_model extends EA_Model
{
    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'id_users_customer' => 'integer',
        'id_packages' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Save (insert or update) a customer package.
     *
     * @param array $customer_package
     *
     * @return int
     *
     * @throws InvalidArgumentException
     */
    public function save(array $customer_package): int
    {
        $this->validate($customer_package);

        if (empty($customer_package['id'])) {
            return $this->insert($customer_package);
        }

        return $this->update($customer_package);
    }

    /**
     * Validate the customer package data.
     *
     * @param array $customer_package
     *
     * @throws InvalidArgumentException
     */
    public function validate(array $customer_package): void
    {
        if (!empty($customer_package['id'])) {
            $count = $this->db->get_where('customer_packages', ['id' => $customer_package['id']])->num_rows();

            if (!$count) {
                throw new InvalidArgumentException(
                    'The provided customer package ID does not exist in the database: ' . $customer_package['id'],
                );
            }
        }

        if (empty($customer_package['id_users_customer']) || !is_numeric($customer_package['id_users_customer'])) {
            throw new InvalidArgumentException('A valid customer ID is required.');
        }

        $customer_exists = $this->db->get_where('users', ['id' => $customer_package['id_users_customer']])->num_rows();

        if (!$customer_exists) {
            throw new InvalidArgumentException(
                'The provided customer ID was not found in the database: ' . $customer_package['id_users_customer'],
            );
        }
    }

    /**
     * Insert a new customer package.
     *
     * @param array $customer_package
     *
     * @return int
     *
     * @throws RuntimeException
     */
    protected function insert(array $customer_package): int
    {
        $customer_package['create_datetime'] = date('Y-m-d H:i:s');
        $customer_package['update_datetime'] = date('Y-m-d H:i:s');

        $items = $customer_package['items'] ?? null;

        unset($customer_package['items']);

        if (!$this->db->insert('customer_packages', $customer_package)) {
            throw new RuntimeException('Could not insert customer package.');
        }

        $customer_package_id = $this->db->insert_id();

        if ($items !== null) {
            $this->load->model('customer_package_items_model');
            $this->customer_package_items_model->set_items($customer_package_id, $items);
        }

        $this->recalculate_status($customer_package_id);

        return $customer_package_id;
    }

    /**
     * Update an existing customer package.
     *
     * @param array $customer_package
     *
     * @return int
     *
     * @throws RuntimeException
     */
    protected function update(array $customer_package): int
    {
        $customer_package['update_datetime'] = date('Y-m-d H:i:s');

        $items = $customer_package['items'] ?? null;

        unset($customer_package['items']);

        if (!$this->db->update('customer_packages', $customer_package, ['id' => $customer_package['id']])) {
            throw new RuntimeException('Could not update customer package.');
        }

        if ($items !== null) {
            $this->load->model('customer_package_items_model');
            $this->customer_package_items_model->set_items($customer_package['id'], $items);
        }

        $this->recalculate_status($customer_package['id']);

        return $customer_package['id'];
    }

    /**
     * Delete a customer package.
     *
     * @param int $customer_package_id
     */
    public function delete(int $customer_package_id): void
    {
        $this->db->delete('customer_packages', ['id' => $customer_package_id]);
    }

    /**
     * Sell a package to a customer.
     *
     * Creates a customer_package snapshot from the package template, including
     * its service items.
     *
     * @param int $customer_id
     * @param int $package_id
     * @param string|null $notes
     *
     * @return int The new customer package ID.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function sell(int $customer_id, int $package_id, ?string $notes = null): int
    {
        $package = $this->db->get_where('packages', ['id' => $package_id, 'is_active' => 1])->row_array();

        if (!$package) {
            throw new InvalidArgumentException('The selected package is not available: ' . $package_id);
        }

        $template_items = $this->db
            ->get_where('package_items', ['id_packages' => $package_id])
            ->result_array();

        if (empty($template_items)) {
            throw new InvalidArgumentException('The selected package has no services defined.');
        }

        $items = [];

        foreach ($template_items as $template_item) {
            $quantity = (int) $template_item['quantity'];

            $items[] = [
                'id_services' => (int) $template_item['id_services'],
                'quantity_total' => $quantity,
                'quantity_remaining' => $quantity,
            ];
        }

        $purchase_date = date('Y-m-d H:i:s');
        $expiry_date = null;

        if (!empty($package['validity_days']) && (int) $package['validity_days'] > 0) {
            $expiry_date = date('Y-m-d H:i:s', strtotime($purchase_date . ' + ' . (int) $package['validity_days'] . ' days'));
        }

        $customer_package = [
            'id_users_customer' => $customer_id,
            'id_packages' => $package_id,
            'purchase_date' => $purchase_date,
            'expiry_date' => $expiry_date,
            'is_active' => 1,
            'notes' => $notes,
            'items' => $items,
        ];

        return $this->save($customer_package);
    }

    /**
     * Find a customer package by ID.
     *
     * @param int $customer_package_id
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function find(int $customer_package_id): array
    {
        $customer_package = $this->db
            ->select('
                customer_packages.*,
                users.first_name AS customer_first_name,
                users.last_name AS customer_last_name,
                users.email AS customer_email,
                packages.name AS package_name,
                packages.price AS package_price,
                packages.validity_days AS package_validity_days
            ')
            ->from('customer_packages')
            ->join('users', 'users.id = customer_packages.id_users_customer', 'inner')
            ->join('packages', 'packages.id = customer_packages.id_packages', 'inner')
            ->where('customer_packages.id', $customer_package_id)
            ->get()
            ->row_array();

        if (!$customer_package) {
            throw new InvalidArgumentException(
                'The provided customer package ID was not found in the database: ' . $customer_package_id,
            );
        }

        $this->cast($customer_package);

        $this->load->model('customer_package_items_model');
        $customer_package['items'] = $this->customer_package_items_model->get_items($customer_package_id);

        $customer_package['is_active'] = $this->recalculate_status($customer_package_id);

        return $customer_package;
    }

    /**
     * Search sold customer packages by keyword and active status.
     *
     * @param string $keyword
     * @param bool|null $is_active
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $order_by
     *
     * @return array
     */
    public function search(
        string $keyword = '',
        ?bool $is_active = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $order_by = null,
    ): array {
        $this->db
            ->select('
                customer_packages.*,
                users.first_name AS customer_first_name,
                users.last_name AS customer_last_name,
                users.email AS customer_email,
                packages.name AS package_name,
                packages.price AS package_price,
                packages.validity_days AS package_validity_days
            ')
            ->from('customer_packages')
            ->join('users', 'users.id = customer_packages.id_users_customer', 'inner')
            ->join('packages', 'packages.id = customer_packages.id_packages', 'inner');

        if ($keyword !== '') {
            $this->db
                ->group_start()
                ->like('users.first_name', $keyword)
                ->or_like('users.last_name', $keyword)
                ->or_like('users.email', $keyword)
                ->or_like('packages.name', $keyword)
                ->group_end();
        }

        if ($is_active !== null) {
            $this->db->where('customer_packages.is_active', $is_active ? 1 : 0);
        }

        if ($limit !== null) {
            $this->db->limit($limit);
        }

        if ($offset !== null) {
            $this->db->offset($offset);
        }

        if ($order_by !== null) {
            $this->db->order_by($this->quote_order_by($order_by));
        }

        $customer_packages = $this->db->get()->result_array();

        $this->load->model('customer_package_items_model');

        foreach ($customer_packages as &$customer_package) {
            $this->cast($customer_package);

            $customer_package['items'] = $this->customer_package_items_model->get_items((int) $customer_package['id']);
            $customer_package['is_active'] = $this->recalculate_status((int) $customer_package['id']);
        }

        return $customer_packages;
    }

    /**
     * Search active customer packages by customer ID.
     *
     * @param int $customer_id
     * @param bool|null $is_active
     *
     * @return array
     */
    public function search_by_customer(int $customer_id, ?bool $is_active = null): array
    {
        $this->db
            ->select('
                customer_packages.*,
                users.first_name AS customer_first_name,
                users.last_name AS customer_last_name,
                users.email AS customer_email,
                packages.name AS package_name,
                packages.price AS package_price,
                packages.validity_days AS package_validity_days
            ')
            ->from('customer_packages')
            ->join('users', 'users.id = customer_packages.id_users_customer', 'inner')
            ->join('packages', 'packages.id = customer_packages.id_packages', 'inner')
            ->where('customer_packages.id_users_customer', $customer_id);

        if ($is_active !== null) {
            $this->db->where('customer_packages.is_active', $is_active ? 1 : 0);
        }

        $customer_packages = $this->db->get()->result_array();

        $this->load->model('customer_package_items_model');

        foreach ($customer_packages as &$customer_package) {
            $this->cast($customer_package);

            $customer_package['items'] = $this->customer_package_items_model->get_items((int) $customer_package['id']);
            $customer_package['is_active'] = $this->recalculate_status((int) $customer_package['id']);
        }

        return $customer_packages;
    }

    /**
     * Consume one remaining use from a customer package item.
     *
     * @param int $customer_package_id
     * @param int $service_id
     *
     * @return bool True if an item was consumed, false otherwise.
     */
    public function consume_item(int $customer_package_id, int $service_id): bool
    {
        $item = $this->db
            ->where('id_customer_packages', $customer_package_id)
            ->where('id_services', $service_id)
            ->where('quantity_remaining >', 0)
            ->order_by('id', 'ASC')
            ->get('customer_package_items')
            ->row_array();

        if (!$item) {
            return false;
        }

        $this->db->where('id', $item['id']);
        $this->db->update('customer_package_items', [
            'quantity_remaining' => (int) $item['quantity_remaining'] - 1,
        ]);

        $this->recalculate_status($customer_package_id);

        return true;
    }

    /**
     * Release one consumed use back to a customer package item.
     *
     * @param int $customer_package_id
     * @param int $service_id
     */
    public function release_item(int $customer_package_id, int $service_id): void
    {
        $item = $this->db
            ->where('id_customer_packages', $customer_package_id)
            ->where('id_services', $service_id)
            ->order_by('id', 'ASC')
            ->get('customer_package_items')
            ->row_array();

        if (!$item) {
            return;
        }

        $this->db->where('id', $item['id']);
        $this->db->update('customer_package_items', [
            'quantity_remaining' => (int) $item['quantity_remaining'] + 1,
        ]);

        $this->recalculate_status($customer_package_id);
    }

    /**
     * Recalculate and persist the is_active flag of a customer package.
     *
     * active = (at least one item remaining > 0) AND (expiry IS NULL OR expiry > now)
     *
     * @param int $customer_package_id
     *
     * @return bool
     */
    public function recalculate_status(int $customer_package_id): bool
    {
        $customer_package = $this->db
            ->get_where('customer_packages', ['id' => $customer_package_id])
            ->row_array();

        if (!$customer_package) {
            return false;
        }

        $has_remaining = $this->db
                ->where('id_customer_packages', $customer_package_id)
                ->where('quantity_remaining >', 0)
                ->count_all_results('customer_package_items') > 0;

        $expiry_date = $customer_package['expiry_date'];
        $not_expired = empty($expiry_date) || $expiry_date > date('Y-m-d H:i:s');

        $is_active = $has_remaining && $not_expired ? 1 : 0;

        if ((int) $customer_package['is_active'] !== $is_active) {
            $this->db->update('customer_packages', ['is_active' => $is_active], ['id' => $customer_package_id]);
        }

        return (bool) $is_active;
    }
}
