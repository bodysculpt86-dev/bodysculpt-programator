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
 * Migration: add a received_at index to the meta_leads table.
 *
 * The call queue is now ordered solely by received_at DESC (the
 * FIELD(call_status, 'de sunat') prioritization was removed), so received_at
 * becomes the single sort column and needs an index to stay fast as the table
 * grows.
 */
class Migration_Add_received_at_index_to_meta_leads extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('meta_leads')) {
            return;
        }

        // Idempotent: only add the index when it is missing.
        $indexes = $this->db->query('SHOW INDEX FROM `' . $this->db->dbprefix('meta_leads') . '`')->result_array();

        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'received_at') {
                return;
            }
        }

        $this->db->query('ALTER TABLE `' . $this->db->dbprefix('meta_leads') . '` ADD KEY `received_at` (`received_at`)');
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if (!$this->db->table_exists('meta_leads')) {
            return;
        }

        $indexes = $this->db->query('SHOW INDEX FROM `' . $this->db->dbprefix('meta_leads') . '`')->result_array();

        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'received_at') {
                $this->db->query('ALTER TABLE `' . $this->db->dbprefix('meta_leads') . '` DROP INDEX `received_at`');
                break;
            }
        }
    }
}
