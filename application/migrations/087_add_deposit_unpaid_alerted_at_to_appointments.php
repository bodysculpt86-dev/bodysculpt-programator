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

class Migration_Add_deposit_unpaid_alerted_at_to_appointments extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('deposit_unpaid_alerted_at', 'appointments')) {
            $fields = [
                'deposit_unpaid_alerted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                    'after' => 'deposit_paid_at',
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
        if ($this->db->field_exists('deposit_unpaid_alerted_at', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'deposit_unpaid_alerted_at');
        }
    }
}
