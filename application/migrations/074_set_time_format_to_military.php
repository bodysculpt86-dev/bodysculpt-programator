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

class Migration_Set_time_format_to_military extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $this->db
            ->where('name', 'time_format')
            ->where('value', 'regular')
            ->update('settings', ['value' => 'military']);
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $this->db
            ->where('name', 'time_format')
            ->where('value', 'military')
            ->update('settings', ['value' => 'regular']);
    }
}
