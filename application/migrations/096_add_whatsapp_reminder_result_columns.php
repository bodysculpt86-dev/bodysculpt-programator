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

class Migration_Add_whatsapp_reminder_result_columns extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('wa_reminder_sent_at', 'appointments')) {
            $fields = [
                'wa_reminder_sent_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                    'after' => 'sms_reminder_error',
                ],
                'wa_reminder_error' => [
                    'type' => 'VARCHAR',
                    'constraint' => '512',
                    'null' => true,
                    'default' => null,
                    'after' => 'wa_reminder_sent_at',
                ],
                'reminder_attempts' => [
                    'type' => 'TINYINT',
                    'constraint' => 3,
                    'null' => false,
                    'default' => 0,
                    'after' => 'wa_reminder_error',
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
        if ($this->db->field_exists('reminder_attempts', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'reminder_attempts');
        }

        if ($this->db->field_exists('wa_reminder_error', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'wa_reminder_error');
        }

        if ($this->db->field_exists('wa_reminder_sent_at', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'wa_reminder_sent_at');
        }
    }
}
