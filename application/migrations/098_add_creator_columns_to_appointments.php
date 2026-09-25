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
 * Migration: record who created each appointment.
 *
 * Until now the appointments table stored when an appointment was booked but never
 * by whom. created_via distinguishes an appointment made by a logged-in user from one
 * submitted through the public booking form, which has no user at all.
 *
 * Both columns are left NULL for rows that predate this migration. Those rows are
 * never attributed retroactively — the UI renders them with an em dash instead.
 */
class Migration_Add_creator_columns_to_appointments extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $this->dbforge->add_column('appointments', [
            'created_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
                'default' => null,
            ],
        ]);

        $this->dbforge->add_column('appointments', [
            'created_via' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
                'default' => null,
            ],
        ]);

        // ON DELETE SET NULL rather than the CASCADE used by the provider/customer
        // keys: removing a user must drop the attribution, not the appointments they
        // created.
        $this->db->query(
            'ALTER TABLE `' .
                $this->db->dbprefix('appointments') .
                '` ADD CONSTRAINT `appointments_users_creator` FOREIGN KEY (`created_by`) REFERENCES `' .
                $this->db->dbprefix('users') .
                '` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
        );
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $this->db->query(
            'ALTER TABLE `' .
                $this->db->dbprefix('appointments') .
                '` DROP FOREIGN KEY `appointments_users_creator`',
        );

        $this->dbforge->drop_column('appointments', 'created_via');

        $this->dbforge->drop_column('appointments', 'created_by');
    }
}
