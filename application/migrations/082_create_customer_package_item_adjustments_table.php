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
 * Create the customer_package_item_adjustments table.
 *
 * Keeps an audit trail for every manual change of quantity_remaining on a
 * customer package item: who changed it, when, old/new values and the reason.
 */
class Migration_Create_customer_package_item_adjustments_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('customer_package_item_adjustments')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'auto_increment' => true,
                ],
                'create_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'id_customer_package_items' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'old_quantity_remaining' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'new_quantity_remaining' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'id_users_modified_by' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'reason' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('id_customer_package_items');

            $this->dbforge->create_table('customer_package_item_adjustments', true, ['engine' => 'InnoDB']);

            $this->db->query('
                ALTER TABLE `' . $this->db->dbprefix('customer_package_item_adjustments') . '`
                ADD CONSTRAINT `' . $this->db->dbprefix('customer_package_item_adjustments') . '_items`
                FOREIGN KEY (`id_customer_package_items`) REFERENCES `' . $this->db->dbprefix('customer_package_items') . '` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
            ');

            $this->db->query('
                ALTER TABLE `' . $this->db->dbprefix('customer_package_item_adjustments') . '`
                ADD CONSTRAINT `' . $this->db->dbprefix('customer_package_item_adjustments') . '_users`
                FOREIGN KEY (`id_users_modified_by`) REFERENCES `' . $this->db->dbprefix('users') . '` (`id`)
                ON DELETE SET NULL ON UPDATE CASCADE
            ');
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('customer_package_item_adjustments')) {
            $this->dbforge->drop_table('customer_package_item_adjustments');
        }
    }
}
