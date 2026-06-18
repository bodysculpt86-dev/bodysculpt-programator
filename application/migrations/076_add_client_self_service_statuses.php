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

class Migration_Add_client_self_service_statuses extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $setting = $this->db->get_where('settings', ['name' => 'appointment_status_options'])->row_array();

        if (empty($setting)) {
            return;
        }

        $options = json_decode($setting['value'], true) ?? [];

        $new_statuses = [
            APPOINTMENT_STATUS_CONFIRMED_BY_CLIENT,
            APPOINTMENT_STATUS_CANCELLED_BY_CLIENT,
        ];

        $options = array_values(array_unique(array_merge($options, $new_statuses)));

        $this->db->update('settings', ['value' => json_encode($options)], ['name' => 'appointment_status_options']);
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $setting = $this->db->get_where('settings', ['name' => 'appointment_status_options'])->row_array();

        if (empty($setting)) {
            return;
        }

        $options = json_decode($setting['value'], true) ?? [];

        $options = array_values(array_diff($options, [
            APPOINTMENT_STATUS_CONFIRMED_BY_CLIENT,
            APPOINTMENT_STATUS_CANCELLED_BY_CLIENT,
        ]));

        $this->db->update('settings', ['value' => json_encode($options)], ['name' => 'appointment_status_options']);
    }
}
