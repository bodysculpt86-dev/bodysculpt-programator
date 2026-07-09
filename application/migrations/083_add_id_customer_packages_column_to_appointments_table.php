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
 * Add the id_customer_packages column to the appointments table.
 *
 * Links an appointment to a sold customer package (subscription). Used in Stage 2B
 * so that Stage 2C knows from which package to decrement remaining sessions.
 */
class Migration_Add_id_customer_packages_column_to_appointments_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('id_customer_packages', 'appointments')) {
            $fields = [
                'id_customer_packages' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'after' => 'id_users_customer',
                ],
            ];

            $this->dbforge->add_column('appointments', $fields);

            $this->db->query('
                ALTER TABLE `' . $this->db->dbprefix('appointments') . '`
                ADD CONSTRAINT `' . $this->db->dbprefix('appointments') . '_customer_packages`
                FOREIGN KEY (`id_customer_packages`) REFERENCES `' . $this->db->dbprefix('customer_packages') . '` (`id`)
                ON DELETE SET NULL ON UPDATE CASCADE
            ');
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('id_customer_packages', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'id_customer_packages');
        }
    }
}
