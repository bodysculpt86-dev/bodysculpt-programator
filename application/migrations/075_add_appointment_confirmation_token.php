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

class Migration_Add_appointment_confirmation_token extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('confirmation_token', 'appointments')) {
            $fields = [
                'confirmation_token' => [
                    'type' => 'VARCHAR',
                    'constraint' => '64',
                    'null' => true,
                    'default' => null,
                    'after' => 'hash',
                ],
            ];

            $this->dbforge->add_column('appointments', $fields);
        }

        $indexes = $this->db->query('SHOW INDEX FROM ' . $this->db->dbprefix('appointments'))->result_array();
        $has_unique = false;

        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'appointments_confirmation_token_unique') {
                $has_unique = true;
                break;
            }
        }

        if (!$has_unique) {
            $this->db->query(
                'ALTER TABLE ' . $this->db->dbprefix('appointments')
                    . ' ADD UNIQUE INDEX appointments_confirmation_token_unique (confirmation_token)'
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('confirmation_token', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'confirmation_token');
        }
    }
}
