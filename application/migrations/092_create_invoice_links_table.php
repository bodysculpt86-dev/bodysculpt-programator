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

class Migration_Create_invoice_links_table extends EA_Migration
{
    /**
     * Upgrade method.
     *
     * Creates the invoice_links table: short public links (/inv/<slug>) that
     * stream an issued invoice's SmartBill PDF. Needed because WhatsApp/Meta
     * document templates require a publicly reachable media URL (Meta fetches
     * the document at send time).
     */
    public function up(): void
    {
        if (!$this->db->table_exists('invoice_links')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'auto_increment' => true,
                ],
                'slug' => [
                    'type' => 'VARCHAR',
                    'constraint' => 16,
                    'null' => false,
                ],
                'id_invoices' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                ],
                'create_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
                'expires_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'clicked_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
                'click_count' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                    'default' => 0,
                ],
            ]);

            $this->dbforge->add_key('id', true);

            $this->dbforge->create_table('invoice_links', true, [
                'ENGINE' => 'InnoDB',
            ]);

            $this->db->query('ALTER TABLE `' . $this->db->dbprefix('invoice_links') . '` ADD UNIQUE KEY `slug` (`slug`)');

            $this->db->query('ALTER TABLE `' . $this->db->dbprefix('invoice_links') . '` ADD KEY `id_invoices` (`id_invoices`)');

            $this->db->query(
                'ALTER TABLE `' .
                    $this->db->dbprefix('invoice_links') .
                    '` ADD CONSTRAINT `ea_invoice_links_invoice` FOREIGN KEY (`id_invoices`) REFERENCES `' .
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
        if ($this->db->table_exists('invoice_links')) {
            $this->dbforge->drop_table('invoice_links');
        }
    }
}
