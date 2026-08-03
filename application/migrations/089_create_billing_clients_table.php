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

class Migration_Create_billing_clients_table extends EA_Migration
{
    /**
     * Upgrade method.
     *
     * Creates the billing_clients table: fiscal clients for the standalone
     * invoicing page (both persoana fizica and persoana juridica).
     */
    public function up(): void
    {
        if (!$this->db->table_exists('billing_clients')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'auto_increment' => true,
                ],
                'type' => [
                    'type' => 'ENUM',
                    'constraint' => ['pf', 'pj'],
                    'null' => false,
                    'default' => 'pf',
                ],
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 256,
                    'null' => false,
                ],
                'cui' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => true,
                    'default' => null,
                ],
                'reg_com' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => true,
                    'default' => null,
                ],
                'address' => [
                    'type' => 'VARCHAR',
                    'constraint' => 512,
                    'null' => true,
                    'default' => null,
                ],
                'city' => [
                    'type' => 'VARCHAR',
                    'constraint' => 128,
                    'null' => true,
                    'default' => null,
                ],
                'county' => [
                    'type' => 'VARCHAR',
                    'constraint' => 128,
                    'null' => true,
                    'default' => null,
                ],
                'email' => [
                    'type' => 'VARCHAR',
                    'constraint' => 128,
                    'null' => true,
                    'default' => null,
                ],
                'phone' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => true,
                    'default' => null,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
            ]);

            $this->dbforge->add_key('id', true);

            $this->dbforge->create_table('billing_clients', true, [
                'ENGINE' => 'InnoDB',
            ]);

            $this->db->query('ALTER TABLE `' . $this->db->dbprefix('billing_clients') . '` ADD KEY `cui` (`cui`)');

            $this->db->query('ALTER TABLE `' . $this->db->dbprefix('billing_clients') . '` ADD KEY `name` (`name`)');
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('billing_clients')) {
            $this->dbforge->drop_table('billing_clients');
        }
    }
}
