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
 * Add the customer_packages privilege column to the roles table.
 *
 * Administrators get full access (view + add + edit + delete = 15).
 * Secretaries get view + add + edit (7) so they can sell subscriptions and
 * apply corrections at the reception desk.
 * Providers and customers get no access.
 */
class Migration_Add_customer_packages_column_to_roles_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('customer_packages', 'roles')) {
            $fields = [
                'customer_packages' => [
                    'type' => 'INT',
                    'constraint' => '11',
                    'null' => true,
                ],
            ];

            $this->dbforge->add_column('roles', $fields);

            $this->db->update('roles', ['customer_packages' => '15'], ['slug' => 'admin']);

            $this->db->update('roles', ['customer_packages' => '7'], ['slug' => 'secretary']);

            $this->db->update('roles', ['customer_packages' => '0'], ['slug' => 'provider']);

            $this->db->update('roles', ['customer_packages' => '0'], ['slug' => 'customer']);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('customer_packages', 'roles')) {
            $this->dbforge->drop_column('roles', 'customer_packages');
        }
    }
}
