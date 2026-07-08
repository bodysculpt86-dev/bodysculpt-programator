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
 * Create the packages and package_items tables.
 *
 * A package is a fixed-price bundle of one or more services with optional
 * validity period. Package lines (items) link a package to services with
 * quantities.
 */
class Migration_Create_packages_tables extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('packages')) {
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
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => '256',
                    'null' => true,
                ],
                'price' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'null' => true,
                ],
                'id_service_categories' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'validity_days' => [
                    'type' => 'INT',
                    'constraint' => 11,
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

            $this->dbforge->create_table('packages', true, ['engine' => 'InnoDB']);

            $this->db->query('
                ALTER TABLE `' . $this->db->dbprefix('packages') . '`
                ADD CONSTRAINT `' . $this->db->dbprefix('packages') . '_service_categories`
                FOREIGN KEY (`id_service_categories`) REFERENCES `' . $this->db->dbprefix('service_categories') . '` (`id`)
                ON DELETE SET NULL ON UPDATE CASCADE
            ');
        }

        if (!$this->db->table_exists('package_items')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'auto_increment' => true,
                ],
                'id_packages' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'id_services' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'quantity' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
            ]);

            $this->dbforge->add_key('id', true);

            $this->dbforge->create_table('package_items', true, ['engine' => 'InnoDB']);

            $this->db->query('
                ALTER TABLE `' . $this->db->dbprefix('package_items') . '`
                ADD CONSTRAINT `' . $this->db->dbprefix('package_items') . '_packages`
                FOREIGN KEY (`id_packages`) REFERENCES `' . $this->db->dbprefix('packages') . '` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
            ');

            $this->db->query('
                ALTER TABLE `' . $this->db->dbprefix('package_items') . '`
                ADD CONSTRAINT `' . $this->db->dbprefix('package_items') . '_services`
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
        if ($this->db->table_exists('package_items')) {
            $this->dbforge->drop_table('package_items');
        }

        if ($this->db->table_exists('packages')) {
            $this->dbforge->drop_table('packages');
        }
    }
}
