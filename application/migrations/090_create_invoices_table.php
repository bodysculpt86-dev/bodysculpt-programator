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

class Migration_Create_invoices_table extends EA_Migration
{
    /**
     * Upgrade method.
     *
     * Creates the invoices table: fiscal invoices issued via SmartBill from the
     * standalone invoicing page.
     *
     * NOTE: `number` is VARCHAR on purpose — SmartBill returns zero-padded
     * invoice numbers ("0044") that must be stored verbatim.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('invoices')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'auto_increment' => true,
                ],
                'billing_client_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'default' => null,
                ],
                'series' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => true,
                    'default' => null,
                ],
                'number' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => true,
                    'default' => null,
                ],
                'issue_date' => [
                    'type' => 'DATE',
                    'null' => true,
                    'default' => null,
                ],
                'payment_method' => [
                    'type' => 'VARCHAR',
                    'constraint' => 32,
                    'null' => true,
                    'default' => null,
                ],
                'subtotal' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'null' => false,
                    'default' => 0.0,
                ],
                'vat_total' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'null' => false,
                    'default' => 0.0,
                ],
                'total' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'null' => false,
                    'default' => 0.0,
                ],
                'smartbill_status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => false,
                    'default' => 'pending',
                ],
                'smartbill_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => true,
                    'default' => null,
                ],
                'smartbill_message' => [
                    'type' => 'VARCHAR',
                    'constraint' => 512,
                    'null' => true,
                    'default' => null,
                ],
                'efactura_status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => true,
                    'default' => null,
                ],
                'is_draft' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'null' => false,
                    'default' => 0,
                ],
                'idempotency_key' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => true,
                    'default' => null,
                ],
                'created_by' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'default' => null,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
            ]);

            $this->dbforge->add_key('id', true);

            $this->dbforge->create_table('invoices', true, [
                'ENGINE' => 'InnoDB',
            ]);

            $this->db->query('ALTER TABLE `' . $this->db->dbprefix('invoices') . '` ADD KEY `billing_client_id` (`billing_client_id`)');

            $this->db->query('ALTER TABLE `' . $this->db->dbprefix('invoices') . '` ADD UNIQUE KEY `idempotency_key` (`idempotency_key`)');

            $this->db->query('ALTER TABLE `' . $this->db->dbprefix('invoices') . '` ADD KEY `series_number` (`series`, `number`)');

            $this->db->query(
                'ALTER TABLE `' .
                    $this->db->dbprefix('invoices') .
                    '` ADD CONSTRAINT `ea_invoices_billing_client` FOREIGN KEY (`billing_client_id`) REFERENCES `' .
                    $this->db->dbprefix('billing_clients') .
                    '` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('invoices')) {
            $this->dbforge->drop_table('invoices');
        }
    }
}
