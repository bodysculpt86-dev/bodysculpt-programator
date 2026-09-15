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
 * Migration: add call workflow columns to the meta_leads table.
 *
 * Adds an internal call-tracking workflow for the receptionists (call_status,
 * call_note, assigned_to, call_updated_at). These columns are purely internal
 * and have no relationship to the Meta Conversions API.
 */
class Migration_Add_call_workflow_columns_to_meta_leads extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('meta_leads')) {
            return;
        }

        if (!$this->db->field_exists('call_status', 'meta_leads')) {
            $this->dbforge->add_column('meta_leads', [
                'call_status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 32,
                    'null' => false,
                    'default' => 'de sunat',
                ],
                'call_note' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'assigned_to' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'default' => null,
                ],
                'call_updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
            ]);
        }

        // Index on call_status (single statement; guarded so it is idempotent).
        $indexes = $this->db->query('SHOW INDEX FROM `' . $this->db->dbprefix('meta_leads') . '`')->result_array();

        $call_status_indexed = false;

        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'call_status') {
                $call_status_indexed = true;
                break;
            }
        }

        if (!$call_status_indexed) {
            $this->db->query('ALTER TABLE `' . $this->db->dbprefix('meta_leads') . '` ADD KEY `call_status` (`call_status`)');
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('call_updated_at', 'meta_leads')) {
            $this->dbforge->drop_column('meta_leads', 'call_updated_at');
        }

        if ($this->db->field_exists('assigned_to', 'meta_leads')) {
            $this->dbforge->drop_column('meta_leads', 'assigned_to');
        }

        if ($this->db->field_exists('call_note', 'meta_leads')) {
            $this->dbforge->drop_column('meta_leads', 'call_note');
        }

        if ($this->db->field_exists('call_status', 'meta_leads')) {
            $this->dbforge->drop_column('meta_leads', 'call_status');
        }
    }
}
