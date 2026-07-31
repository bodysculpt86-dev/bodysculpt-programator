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

class Migration_Add_deposit_columns_to_appointments extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('deposit_status', 'appointments')) {
            $fields = [
                'deposit_status' => [
                    'type' => 'VARCHAR',
                    'constraint' => '20',
                    'null' => false,
                    'default' => 'none',
                    'after' => 'price',
                ],
                'deposit_amount' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'null' => true,
                    'default' => null,
                    'after' => 'deposit_status',
                ],
                'stripe_session_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => '255',
                    'null' => true,
                    'default' => null,
                    'after' => 'deposit_amount',
                ],
                'payment_link_sent_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                    'after' => 'stripe_session_id',
                ],
                'deposit_paid_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                    'after' => 'payment_link_sent_at',
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
        if ($this->db->field_exists('deposit_paid_at', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'deposit_paid_at');
        }

        if ($this->db->field_exists('payment_link_sent_at', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'payment_link_sent_at');
        }

        if ($this->db->field_exists('stripe_session_id', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'stripe_session_id');
        }

        if ($this->db->field_exists('deposit_amount', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'deposit_amount');
        }

        if ($this->db->field_exists('deposit_status', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'deposit_status');
        }
    }
}
