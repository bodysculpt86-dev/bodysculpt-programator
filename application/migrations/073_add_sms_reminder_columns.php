<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

class Migration_Add_sms_reminder_columns extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('sms_reminder_sent_at', 'appointments')) {
            $fields = [
                'sms_reminder_sent_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                    'after' => 'status',
                ],
                'sms_reminder_error' => [
                    'type' => 'VARCHAR',
                    'constraint' => '512',
                    'null' => true,
                    'default' => null,
                    'after' => 'sms_reminder_sent_at',
                ],
            ];

            $this->dbforge->add_column('appointments', $fields);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('sms_reminder_error', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'sms_reminder_error');
        }

        if ($this->db->field_exists('sms_reminder_sent_at', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'sms_reminder_sent_at');
        }
    }
}
