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

class Migration_Create_invoice_items_table extends EA_Migration
{
    /**
     * Upgrade method.
     *
     * Creates the invoice_items table: the lines of each fiscal invoice.
     * source_type includes 'product' from day one so the future products
     * catalog (deferred phase) plugs in without a schema change.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('invoice_items')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'auto_increment' => true,
                ],
                'invoice_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                ],
                'source_type' => [
                    'type' => 'ENUM',
                    'constraint' => ['service', 'package', 'product', 'manual'],
                    'null' => false,
                    'default' => 'manual',
                ],
                'source_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'default' => null,
                ],
                'description' => [
                    'type' => 'VARCHAR',
                    'constraint' => 256,
                    'null' => false,
                ],
                'qty' => [
                    'type' => 'DECIMAL',
                    'constraint' => '8,2',
                    'null' => false,
                    'default' => 1.0,
                ],
                'unit_price' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'null' => false,
                    'default' => 0.0,
                ],
                'vat_rate' => [
                    'type' => 'DECIMAL',
                    'constraint' => '4,2',
                    'null' => false,
                    'default' => 19.0,
                ],
                'line_total' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'null' => false,
                    'default' => 0.0,
                ],
            ]);

            $this->dbforge->add_key('id', true);

            $this->dbforge->create_table('invoice_items', true, [
                'ENGINE' => 'InnoDB',
            ]);

            $this->db->query('ALTER TABLE `' . $this->db->dbprefix('invoice_items') . '` ADD KEY `invoice_id` (`invoice_id`)');

            $this->db->query(
                'ALTER TABLE `' .
                    $this->db->dbprefix('invoice_items') .
                    '` ADD CONSTRAINT `ea_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `' .
                    $this->db->dbprefix('invoices') .
                    '` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('invoice_items')) {
            $this->dbforge->drop_table('invoice_items');
        }
    }
}
