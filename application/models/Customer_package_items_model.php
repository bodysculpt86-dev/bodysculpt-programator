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
 * Customer package items model.
 *
 * Handles the database operations of the sold package service lines and the
 * audit trail for manual adjustments of quantity_remaining.
 *
 * @package Models
 */
class Customer_package_items_model extends EA_Model
{
    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'id_customer_packages' => 'integer',
        'id_services' => 'integer',
        'quantity_total' => 'integer',
        'quantity_remaining' => 'integer',
    ];

    /**
     * Get the items of a customer package, enriched with service names.
     *
     * @param int $customer_package_id
     *
     * @return array
     */
    public function get_items(int $customer_package_id): array
    {
        $items = $this->db
            ->select('customer_package_items.*, services.name AS service_name')
            ->from('customer_package_items')
            ->join('services', 'services.id = customer_package_items.id_services', 'inner')
            ->where('customer_package_items.id_customer_packages', $customer_package_id)
            ->get()
            ->result_array();

        foreach ($items as &$item) {
            $this->cast($item);
        }

        return $items;
    }

    /**
     * Replace the items of a customer package.
     *
     * Used during the initial sale to snapshot the template lines.
     *
     * @param int $customer_package_id
     * @param array $items
     */
    public function set_items(int $customer_package_id, array $items): void
    {
        $this->db->delete('customer_package_items', ['id_customer_packages' => $customer_package_id]);

        foreach ($items as $item) {
            $this->db->insert('customer_package_items', [
                'id_customer_packages' => $customer_package_id,
                'id_services' => $item['id_services'],
                'quantity_total' => $item['quantity_total'],
                'quantity_remaining' => $item['quantity_remaining'],
            ]);
        }
    }

    /**
     * Manually adjust the remaining quantity of an item.
     *
     * Validates the new value, writes an audit log and updates the item.
     *
     * @param int $item_id
     * @param int $new_remaining
     * @param string|null $reason
     * @param int|null $modified_by_user_id
     *
     * @throws InvalidArgumentException
     */
    public function adjust(int $item_id, int $new_remaining, ?string $reason = null, ?int $modified_by_user_id = null): void
    {
        if ($new_remaining < 0) {
            throw new InvalidArgumentException('Quantity remaining cannot be negative.');
        }

        $item = $this->db->get_where('customer_package_items', ['id' => $item_id])->row_array();

        if (!$item) {
            throw new InvalidArgumentException('Customer package item not found: ' . $item_id);
        }

        $old_remaining = (int) $item['quantity_remaining'];

        if ($old_remaining === $new_remaining) {
            return;
        }

        $this->db->insert('customer_package_item_adjustments', [
            'create_datetime' => date('Y-m-d H:i:s'),
            'id_customer_package_items' => $item_id,
            'old_quantity_remaining' => $old_remaining,
            'new_quantity_remaining' => $new_remaining,
            'id_users_modified_by' => $modified_by_user_id,
            'reason' => $reason,
        ]);

        $this->db->update('customer_package_items', [
            'quantity_remaining' => $new_remaining,
        ], ['id' => $item_id]);
    }

    /**
     * Get the adjustment history for an item.
     *
     * @param int $item_id
     *
     * @return array
     */
    public function get_adjustments(int $item_id): array
    {
        return $this->db
            ->select('
                customer_package_item_adjustments.*,
                users.first_name AS modifier_first_name,
                users.last_name AS modifier_last_name
            ')
            ->from('customer_package_item_adjustments')
            ->join('users', 'users.id = customer_package_item_adjustments.id_users_modified_by', 'left')
            ->where('customer_package_item_adjustments.id_customer_package_items', $item_id)
            ->order_by('customer_package_item_adjustments.create_datetime', 'DESC')
            ->get()
            ->result_array();
    }
}
