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
 * Packages model.
 *
 * Handles all the database operations of the package resource.
 *
 * @package Models
 */
class Packages_model extends EA_Model
{
    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'price' => 'float',
        'id_service_categories' => 'integer',
        'validity_days' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Save (insert or update) a package.
     *
     * @param array $package Associative array with the package data.
     *
     * @return int Returns the package ID.
     *
     * @throws InvalidArgumentException
     */
    public function save(array $package): int
    {
        $this->validate($package);

        if (empty($package['id'])) {
            return $this->insert($package);
        } else {
            return $this->update($package);
        }
    }

    /**
     * Validate the package data.
     *
     * @param array $package Associative array with the package data.
     *
     * @throws InvalidArgumentException
     */
    public function validate(array $package): void
    {
        // If a package ID is provided then check whether the record really exists in the database.
        if (!empty($package['id'])) {
            $count = $this->db->get_where('packages', ['id' => $package['id']])->num_rows();

            if (!$count) {
                throw new InvalidArgumentException(
                    'The provided package ID does not exist in the database: ' . $package['id'],
                );
            }
        }

        // Make sure all required fields are provided.
        if (empty($package['name'])) {
            throw new InvalidArgumentException('Not all required fields are provided: ' . print_r($package, true));
        }

        if (!isset($package['price']) || !is_numeric($package['price'])) {
            throw new InvalidArgumentException('The package price is invalid: ' . ($package['price'] ?? 'null'));
        }

        if (isset($package['validity_days']) && (int) $package['validity_days'] < 1) {
            throw new InvalidArgumentException(
                'The package validity days must be at least 1: ' . $package['validity_days'],
            );
        }

        // If a category was provided then make sure it really exists in the database.
        if (!empty($package['id_service_categories'])) {
            $count = $this->db
                ->get_where('service_categories', ['id' => $package['id_service_categories']])
                ->num_rows();

            if (!$count) {
                throw new InvalidArgumentException(
                    'The provided category ID was not found in the database: ' . $package['id_service_categories'],
                );
            }
        }

        // Validate package items.
        $items = $package['items'] ?? [];

        if (empty($items)) {
            throw new InvalidArgumentException('A package must contain at least one service item.');
        }

        foreach ($items as $index => $item) {
            if (empty($item['id_services']) || !is_numeric($item['id_services'])) {
                throw new InvalidArgumentException(
                    'Invalid service ID for package item at index ' . $index . ': ' . print_r($item, true),
                );
            }

            if (empty($item['quantity']) || (int) $item['quantity'] < 1) {
                throw new InvalidArgumentException(
                    'Invalid quantity for package item at index ' . $index . ': ' . print_r($item, true),
                );
            }

            $service_exists = $this->db->get_where('services', ['id' => $item['id_services']])->num_rows();

            if (!$service_exists) {
                throw new InvalidArgumentException(
                    'The provided service ID was not found in the database: ' . $item['id_services'],
                );
            }
        }
    }

    /**
     * Insert a new package into the database.
     *
     * @param array $package Associative array with the package data.
     *
     * @return int Returns the package ID.
     *
     * @throws RuntimeException
     */
    protected function insert(array $package): int
    {
        $package['create_datetime'] = date('Y-m-d H:i:s');
        $package['update_datetime'] = date('Y-m-d H:i:s');

        $items = $package['items'] ?? [];

        unset($package['items']);

        if (!$this->db->insert('packages', $package)) {
            throw new RuntimeException('Could not insert package.');
        }

        $package_id = $this->db->insert_id();

        $this->set_items($package_id, $items);

        return $package_id;
    }

    /**
     * Update an existing package.
     *
     * @param array $package Associative array with the package data.
     *
     * @return int Returns the package ID.
     *
     * @throws RuntimeException
     */
    protected function update(array $package): int
    {
        $package['update_datetime'] = date('Y-m-d H:i:s');

        $items = $package['items'] ?? null;

        unset($package['items']);

        if (!$this->db->update('packages', $package, ['id' => $package['id']])) {
            throw new RuntimeException('Could not update package.');
        }

        if ($items !== null) {
            $this->set_items($package['id'], $items);
        }

        return $package['id'];
    }

    /**
     * Remove an existing package from the database.
     *
     * TODO Etapa 2: when packages have associated sales, switch to soft delete
     * or block deletion if sales exist — otherwise sales records become orphaned.
     *
     * @param int $package_id Package ID.
     *
     * @throws RuntimeException
     */
    public function delete(int $package_id): void
    {
        $this->db->delete('packages', ['id' => $package_id]);
    }

    /**
     * Get the package items.
     *
     * @param int $package_id Package ID.
     *
     * @return array Returns an array of package items with service names and prices.
     */
    public function get_items(int $package_id): array
    {
        return $this->db
            ->select('package_items.*, services.name AS service_name, services.price AS service_price')
            ->from('package_items')
            ->join('services', 'services.id = package_items.id_services', 'inner')
            ->where('package_items.id_packages', $package_id)
            ->get()
            ->result_array();
    }

    /**
     * Save the package items.
     *
     * @param int $package_id Package ID.
     * @param array $items Package items.
     */
    public function set_items(int $package_id, array $items): void
    {
        // Re-insert the package items.
        $this->db->delete('package_items', ['id_packages' => $package_id]);

        foreach ($items as $item) {
            $package_item = [
                'id_packages' => $package_id,
                'id_services' => $item['id_services'],
                'quantity' => $item['quantity'],
            ];

            $this->db->insert('package_items', $package_item);
        }
    }

    /**
     * Get a specific package from the database.
     *
     * @param int $package_id The ID of the record to be returned.
     *
     * @return array Returns an array with the package data.
     *
     * @throws InvalidArgumentException
     */
    public function find(int $package_id): array
    {
        $package = $this->db->get_where('packages', ['id' => $package_id])->row_array();

        if (!$package) {
            throw new InvalidArgumentException('The provided package ID was not found in the database: ' . $package_id);
        }

        $this->cast($package);

        $package['items'] = $this->get_items($package_id);

        return $package;
    }

    /**
     * Get all packages that match the provided criteria.
     *
     * @param array|string|null $where Where conditions
     * @param int|null $limit Record limit.
     * @param int|null $offset Record offset.
     * @param string|null $order_by Order by.
     *
     * @return array Returns an array of packages.
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

        if ($order_by !== null) {
            $this->db->order_by($this->quote_order_by($order_by));
        }

        $packages = $this->db->get('packages', $limit, $offset)->result_array();

        foreach ($packages as &$package) {
            $this->cast($package);
            $package['items'] = $this->get_items($package['id']);
        }

        return $packages;
    }

    /**
     * Search packages by keyword and optional filters.
     *
     * @param string $keyword Search keyword.
     * @param int|null $category_id Filter by service category ID.
     * @param bool|null $is_active Filter by active status.
     * @param int|null $limit Record limit.
     * @param int|null $offset Record offset.
     * @param string|null $order_by Order by.
     *
     * @return array Returns an array of packages.
     */
    public function search(
        string $keyword = '',
        ?int $category_id = null,
        ?bool $is_active = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $order_by = null,
    ): array {
        $this->db
            ->select('packages.*, service_categories.name AS category_name')
            ->from('packages')
            ->join('service_categories', 'service_categories.id = packages.id_service_categories', 'left');

        if ($keyword !== '') {
            $this->db
                ->group_start()
                ->like('packages.name', $keyword)
                ->or_like('packages.notes', $keyword)
                ->group_end();
        }

        if ($category_id !== null) {
            $this->db->where('packages.id_service_categories', $category_id);
        }

        if ($is_active !== null) {
            $this->db->where('packages.is_active', $is_active ? 1 : 0);
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

        $packages = $this->db->get()->result_array();

        foreach ($packages as &$package) {
            $this->cast($package);
            $package['items'] = $this->get_items($package['id']);
        }

        return $packages;
    }

    /**
     * Get packages as options for dropdowns.
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

        $packages = $this->db
            ->select('id, name')
            ->from('packages')
            ->where('is_active', 1)
            ->order_by('name')
            ->get()
            ->result_array();

        $options = [];

        foreach ($packages as $package) {
            $options[] = [
                'value' => (int) $package['id'],
                'label' => $package['name'],
            ];
        }

        return $options;
    }
}
