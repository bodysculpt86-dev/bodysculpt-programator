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
 * Migration: add AI call result columns to the meta_leads table.
 *
 * Stores the outcome Autocalls (the AI caller) reports back for a lead, via the
 * Webhooks_autocalls webhook. Purely a display-only addition on top of the
 * existing call_status/assigned_to receptionist workflow — it does not read or
 * write any of those columns.
 */
class Migration_Add_ai_call_result_columns_to_meta_leads extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('meta_leads')) {
            return;
        }

        if (!$this->db->field_exists('ai_call_classification', 'meta_leads')) {
            $this->dbforge->add_column('meta_leads', [
                'ai_call_classification' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => true,
                    'default' => null,
                ],
                'ai_call_summary' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'ai_call_desired_procedure' => [
                    'type' => 'VARCHAR',
                    'constraint' => 256,
                    'null' => true,
                    'default' => null,
                ],
                'ai_call_attempt_number' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'default' => null,
                ],
                'ai_call_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
                'ai_call_recording_url' => [
                    'type' => 'VARCHAR',
                    'constraint' => 512,
                    'null' => true,
                    'default' => null,
                ],
                'ai_call_updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
            ]);
        }

        // Index on ai_call_classification (single statement, guarded so it is
        // idempotent) — the admin page will filter/highlight by this column.
        $indexes = $this->db->query('SHOW INDEX FROM `' . $this->db->dbprefix('meta_leads') . '`')->result_array();

        $classification_indexed = false;

        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'ai_call_classification') {
                $classification_indexed = true;
                break;
            }
        }

        if (!$classification_indexed) {
            $this->db->query(
                'ALTER TABLE `' . $this->db->dbprefix('meta_leads') . '` ADD KEY `ai_call_classification` (`ai_call_classification`)',
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if (!$this->db->table_exists('meta_leads')) {
            return;
        }

        if ($this->db->field_exists('ai_call_updated_at', 'meta_leads')) {
            $this->dbforge->drop_column('meta_leads', 'ai_call_updated_at');
        }

        if ($this->db->field_exists('ai_call_recording_url', 'meta_leads')) {
            $this->dbforge->drop_column('meta_leads', 'ai_call_recording_url');
        }

        if ($this->db->field_exists('ai_call_at', 'meta_leads')) {
            $this->dbforge->drop_column('meta_leads', 'ai_call_at');
        }

        if ($this->db->field_exists('ai_call_attempt_number', 'meta_leads')) {
            $this->dbforge->drop_column('meta_leads', 'ai_call_attempt_number');
        }

        if ($this->db->field_exists('ai_call_desired_procedure', 'meta_leads')) {
            $this->dbforge->drop_column('meta_leads', 'ai_call_desired_procedure');
        }

        if ($this->db->field_exists('ai_call_summary', 'meta_leads')) {
            $this->dbforge->drop_column('meta_leads', 'ai_call_summary');
        }

        if ($this->db->field_exists('ai_call_classification', 'meta_leads')) {
            $this->dbforge->drop_column('meta_leads', 'ai_call_classification');
        }
    }
}
