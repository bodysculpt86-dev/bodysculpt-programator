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
 * Create the customer_packages and customer_package_items tables.
 *
 * A customer_package is a sold subscription/package instance that belongs to a
 * customer. Its items are a snapshot of the package template at the moment of
 * sale, so later template changes do not affect already sold subscriptions.
 */
class Migration_Create_customer_packages_tables extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('customer_packages')) {
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
                'update_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'id_users_customer' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'id_packages' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'purchase_date' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'expiry_date' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'is_active' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'null' => true,
                    'default' => 1,
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('id_users_customer');

            $this->dbforge->create_table('customer_packages', true, ['engine' => 'InnoDB']);

            $this->db->query('
                ALTER TABLE `' . $this->db->dbprefix('customer_packages') . '`
                ADD CONSTRAINT `' . $this->db->dbprefix('customer_packages') . '_users`
                FOREIGN KEY (`id_users_customer`) REFERENCES `' . $this->db->dbprefix('users') . '` (`id`)
                ON DELETE RESTRICT ON UPDATE CASCADE
            ');

            $this->db->query('
                ALTER TABLE `' . $this->db->dbprefix('customer_packages') . '`
                ADD CONSTRAINT `' . $this->db->dbprefix('customer_packages') . '_packages`
                FOREIGN KEY (`id_packages`) REFERENCES `' . $this->db->dbprefix('packages') . '` (`id`)
                ON DELETE RESTRICT ON UPDATE CASCADE
            ');
        }

        if (!$this->db->table_exists('customer_package_items')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'auto_increment' => true,
                ],
                'id_customer_packages' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'id_services' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'quantity_total' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'quantity_remaining' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
            ]);

            $this->dbforge->add_key('id', true);

            $this->dbforge->create_table('customer_package_items', true, ['engine' => 'InnoDB']);

            $this->db->query('
                ALTER TABLE `' . $this->db->dbprefix('customer_package_items') . '`
                ADD CONSTRAINT `' . $this->db->dbprefix('customer_package_items') . '_customer_packages`
                FOREIGN KEY (`id_customer_packages`) REFERENCES `' . $this->db->dbprefix('customer_packages') . '` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
            ');

            $this->db->query('
                ALTER TABLE `' . $this->db->dbprefix('customer_package_items') . '`
                ADD CONSTRAINT `' . $this->db->dbprefix('customer_package_items') . '_services`
                FOREIGN KEY (`id_services`) REFERENCES `' . $this->db->dbprefix('services') . '` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
            ');
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('customer_package_items')) {
            $this->dbforge->drop_table('customer_package_items');
        }

        if ($this->db->table_exists('customer_packages')) {
            $this->dbforge->drop_table('customer_packages');
        }
    }
}
