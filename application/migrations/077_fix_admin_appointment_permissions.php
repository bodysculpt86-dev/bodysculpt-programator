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
 * Restore full appointment permissions for the administrator role.
 *
 * A recent regression removed the "add" permission from the admin role's
 * appointment privileges, preventing admins from creating appointments for
 * employees. This migration restores the full set of appointment permissions
 * (view + add + edit + delete = 15) for the administrator role only.
 */
class Migration_Fix_admin_appointment_permissions extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $this->db->update('roles', ['appointments' => 15], ['slug' => 'admin']);
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        // Intentionally left blank: we cannot know the previous (broken) value
        // and rolling back the permission fix would re-introduce the regression.
    }
}
