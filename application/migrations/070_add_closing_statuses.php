<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * @property CI_DB_query_builder $db
 * @property CI_DB_forge $dbforge
 */
class Migration_Add_closing_statuses extends CI_Migration
{
    /**
     * The closing status values that are added to the appointment status options.
     */
    private const CLOSING_STATUSES = [
        'Nu s-a prezentat',
        'A plătit cash',
        'A plătit card',
        'Barter',
        'Cadou',
    ];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        // 1. Add closing statuses to the global appointment status options if missing.
        $setting = $this->db->get_where('settings', ['name' => 'appointment_status_options'])->row_array();

        if (!empty($setting)) {
            $options = json_decode($setting['value'], true) ?: [];

            foreach (self::CLOSING_STATUSES as $status) {
                if (!in_array($status, $options, true)) {
                    $options[] = $status;
                }
            }

            $this->db->update('settings', ['value' => json_encode($options)], ['name' => 'appointment_status_options']);
        }

        // 2. Create a dedicated setting for the closing statuses dropdown.
        if (!$this->db->get_where('settings', ['name' => 'appointment_closing_statuses'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'appointment_closing_statuses',
                'value' => json_encode(self::CLOSING_STATUSES),
            ]);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        // 1. Remove closing statuses from the global appointment status options.
        $setting = $this->db->get_where('settings', ['name' => 'appointment_status_options'])->row_array();

        if (!empty($setting)) {
            $options = json_decode($setting['value'], true) ?: [];

            $options = array_values(array_diff($options, self::CLOSING_STATUSES));

            $this->db->update('settings', ['value' => json_encode($options)], ['name' => 'appointment_status_options']);
        }

        // 2. Remove the dedicated closing statuses setting.
        if ($this->db->get_where('settings', ['name' => 'appointment_closing_statuses'])->num_rows()) {
            $this->db->delete('settings', ['name' => 'appointment_closing_statuses']);
        }
    }
}
