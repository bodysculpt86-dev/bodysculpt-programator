<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

class Migration_Disable_public_booking extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $this->db->update('settings', ['value' => '1'], ['name' => 'disable_booking']);

        $this->db->update('settings', [
            'value' => 'Rezervările online sunt dezactivate. Pentru programări, vă rugăm să contactați clinica.',
        ], ['name' => 'disable_booking_message']);
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $this->db->update('settings', ['value' => '0'], ['name' => 'disable_booking']);

        $this->db->update('settings', [
            'value' => 'Thanks for stopping by! We are not accepting new appointments at the moment, please check back again later.',
        ], ['name' => 'disable_booking_message']);
    }
}
