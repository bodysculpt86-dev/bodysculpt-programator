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
 * Migration: create the meta_leads table.
 *
 * Stores Meta Lead Ads leads received via the leadgen webhook. Raw form field
 * data is kept in `form_fields` (JSON) because Instant Forms can have arbitrary
 * custom questions; the extracted name/email/phone columns are used for display
 * and for the lead -> customer conversion flow.
 */
class Migration_Create_meta_leads_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('meta_leads')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'auto_increment' => true,
                ],
                'leadgen_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => false,
                ],
                'page_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => true,
                    'default' => null,
                ],
                'form_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => true,
                    'default' => null,
                ],
                'first_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 256,
                    'null' => true,
                    'default' => null,
                ],
                'last_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 512,
                    'null' => true,
                    'default' => null,
                ],
                'email' => [
                    'type' => 'VARCHAR',
                    'constraint' => 512,
                    'null' => true,
                    'default' => null,
                ],
                'phone_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => 128,
                    'null' => true,
                    'default' => null,
                ],
                'form_fields' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 32,
                    'null' => false,
                    'default' => 'new',
                ],
                'received_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'converted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
                'customer_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'default' => null,
                ],
                'capi_lead_event_sent' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'null' => false,
                    'default' => 0,
                ],
                'capi_converted_event_sent' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'null' => false,
                    'default' => 0,
                ],
                'create_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
                'update_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
            ]);

            $this->dbforge->add_key('id', true);

            $this->dbforge->create_table('meta_leads', true, [
                'ENGINE' => 'InnoDB',
            ]);

            // Idempotent webhook re-delivery: a leadgen_id must only ever be stored once.
            $this->db->query(
                'ALTER TABLE `' . $this->db->dbprefix('meta_leads') . '` ADD UNIQUE KEY `leadgen_id` (`leadgen_id`)',
            );

            $this->db->query(
                'ALTER TABLE `' . $this->db->dbprefix('meta_leads') . '` ADD KEY `customer_id` (`customer_id`), ADD KEY `status` (`status`)',
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('meta_leads')) {
            $this->dbforge->drop_table('meta_leads');
        }
    }
}
