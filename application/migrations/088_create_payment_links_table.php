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

class Migration_Create_payment_links_table extends EA_Migration
{
    /**
     * Upgrade method.
     *
     * Creates the payment_links table used by the internal URL shortener for
     * Stripe Checkout links sent via WhatsApp (/pay/<slug> redirects).
     */
    public function up(): void
    {
        if (!$this->db->table_exists('payment_links')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'auto_increment' => true,
                ],
                'slug' => [
                    'type' => 'VARCHAR',
                    'constraint' => 16,
                    'null' => false,
                ],
                'stripe_url' => [
                    'type' => 'TEXT',
                    'null' => false,
                ],
                'id_appointments' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'default' => null,
                ],
                'create_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
                'expires_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'clicked_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
                ],
                'click_count' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                    'default' => 0,
                ],
            ]);

            $this->dbforge->add_key('id', true);

            $this->dbforge->create_table('payment_links', true, [
                'ENGINE' => 'InnoDB',
            ]);

            $this->db->query(
                'ALTER TABLE `' . $this->db->dbprefix('payment_links') . '` ADD UNIQUE KEY `slug` (`slug`)',
            );

            $this->db->query(
                'ALTER TABLE `' .
                    $this->db->dbprefix('payment_links') .
                    '` ADD KEY `id_appointments` (`id_appointments`), ADD KEY `expires_at` (`expires_at`)',
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('payment_links')) {
            $this->dbforge->drop_table('payment_links');
        }
    }
}
