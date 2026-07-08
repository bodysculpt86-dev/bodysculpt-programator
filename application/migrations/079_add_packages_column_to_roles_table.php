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
 * Add the packages privilege column to the roles table.
 *
 * Administrators get full access (view + add + edit + delete = 15), all other
 * roles get 0. In a future stage secretaries can be granted access for selling
 * packages at the reception desk.
 */
class Migration_Add_packages_column_to_roles_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('packages', 'roles')) {
            $fields = [
                'packages' => [
                    'type' => 'INT',
                    'constraint' => '11',
                    'null' => true,
                ],
            ];

            $this->dbforge->add_column('roles', $fields);

            $this->db->update('roles', ['packages' => '15'], ['slug' => 'admin']);

            $this->db->update('roles', ['packages' => '0'], ['slug !=' => 'admin']);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('packages', 'roles')) {
            $this->dbforge->drop_column('roles', 'packages');
        }
    }
}
