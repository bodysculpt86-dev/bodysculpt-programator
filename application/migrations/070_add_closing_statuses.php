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
     * Old closing/payment statuses that must be removed from the main status list.
     */
    private const OLD_STATUSES = [
        'Nu s-a prezentat',
        'A plătit cash',
        'A plătit card',
        'Barter',
        'Cadou',
    ];

    /**
     * New EvoBeauty closing / payment status values used only in the close dropdown.
     */
    private const NEW_STATUSES = [
        'Cash',
        'Card',
        'Barter',
        'Protocol',
        'Banca/OP',
        'Consum abonament',
        'Voucher',
        'Nu s-a prezentat',
    ];

    /**
     * Map old status values to the new equivalents for existing appointments.
     */
    private const STATUS_MAP = [
        'A plătit cash' => 'Cash',
        'A plătit card' => 'Card',
        'Cadou' => 'Voucher',
    ];

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        // 1. Remove old closing/payment statuses from the main appointment status options.
        // The "Stare" dropdown must keep only the original operational statuses.
        $setting = $this->db->get_where('settings', ['name' => 'appointment_status_options'])->row_array();

        if (!empty($setting)) {
            $options = json_decode($setting['value'], true) ?: [];

            $options = array_values(array_diff($options, array_merge(self::OLD_STATUSES, self::NEW_STATUSES)));

            $this->db->update('settings', ['value' => json_encode($options)], ['name' => 'appointment_status_options']);
        }

        // 2. Create or replace the dedicated closing statuses setting used by the close dropdown.
        if ($this->db->get_where('settings', ['name' => 'appointment_closing_statuses'])->num_rows()) {
            $this->db->update('settings', ['value' => json_encode(self::NEW_STATUSES)], ['name' => 'appointment_closing_statuses']);
        } else {
            $this->db->insert('settings', [
                'name' => 'appointment_closing_statuses',
                'value' => json_encode(self::NEW_STATUSES),
            ]);
        }

        // 3. Migrate existing appointments that used the old status values.
        foreach (self::STATUS_MAP as $old_status => $new_status) {
            $this->db->update('appointments', ['status' => $new_status], ['status' => $old_status]);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        // 1. Reverse the status remap on existing appointments.
        foreach (array_flip(self::STATUS_MAP) as $new_status => $old_status) {
            $this->db->update('appointments', ['status' => $old_status], ['status' => $new_status]);
        }

        // 2. Restore old closing statuses to the main appointment status options.
        $setting = $this->db->get_where('settings', ['name' => 'appointment_status_options'])->row_array();

        if (!empty($setting)) {
            $options = json_decode($setting['value'], true) ?: [];

            $options = array_values(array_unique(array_merge($options, self::OLD_STATUSES)));

            $this->db->update('settings', ['value' => json_encode($options)], ['name' => 'appointment_status_options']);
        }

        // 3. Remove the dedicated closing statuses setting.
        if ($this->db->get_where('settings', ['name' => 'appointment_closing_statuses'])->num_rows()) {
            $this->db->delete('settings', ['name' => 'appointment_closing_statuses']);
        }
    }
}
