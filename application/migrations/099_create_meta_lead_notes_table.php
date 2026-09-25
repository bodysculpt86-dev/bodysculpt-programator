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
 * Migration: create the meta_lead_notes table.
 *
 * Until now a lead had a single call_note column, so every new note overwrote the previous one and
 * the lead's call history was lost. This table keeps one row per note, with the author and the
 * moment it was written.
 *
 * The call_note column is not dropped: it keeps mirroring the most recent note so anything already
 * reading it continues to work. Existing notes are copied in as the first history entry, with no
 * author — the author was never recorded, and it is not guessed retroactively.
 */
class Migration_Create_meta_lead_notes_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if ($this->db->table_exists('meta_lead_notes')) {
            return;
        }

        $this->dbforge->add_field([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => true,
            ],
            'id_meta_leads' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
            ],
            'note' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'id_users' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
                'default' => null,
            ],
            'create_datetime' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->dbforge->add_key('id', true);

        $this->dbforge->create_table('meta_lead_notes', true, [
            'ENGINE' => 'InnoDB',
        ]);

        // Deleting a lead takes its notes with it.
        $this->db->query(
            'ALTER TABLE `' .
                $this->db->dbprefix('meta_lead_notes') .
                '` ADD CONSTRAINT `meta_lead_notes_meta_leads` FOREIGN KEY (`id_meta_leads`) REFERENCES `' .
                $this->db->dbprefix('meta_leads') .
                '` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
        );

        // Removing a user drops the attribution, not the note they wrote.
        $this->db->query(
            'ALTER TABLE `' .
                $this->db->dbprefix('meta_lead_notes') .
                '` ADD CONSTRAINT `meta_lead_notes_users` FOREIGN KEY (`id_users`) REFERENCES `' .
                $this->db->dbprefix('users') .
                '` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
        );

        // Existing notes become the first entry of the history, without an author. call_updated_at
        // is the closest thing to "when this note was written"; on leads whose status alone was
        // ever touched there is none, so the row's own update time is used instead.
        $this->db->query(
            'INSERT INTO `' .
                $this->db->dbprefix('meta_lead_notes') .
                '` (`id_meta_leads`, `note`, `id_users`, `create_datetime`) ' .
                'SELECT `id`, `call_note`, NULL, COALESCE(`call_updated_at`, `update_datetime`, `received_at`) ' .
                'FROM `' .
                $this->db->dbprefix('meta_leads') .
                "` WHERE `call_note` IS NOT NULL AND TRIM(`call_note`) <> ''",
        );
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if (!$this->db->table_exists('meta_lead_notes')) {
            return;
        }

        $this->db->query(
            'ALTER TABLE `' .
                $this->db->dbprefix('meta_lead_notes') .
                '` DROP FOREIGN KEY `meta_lead_notes_users`',
        );

        $this->db->query(
            'ALTER TABLE `' .
                $this->db->dbprefix('meta_lead_notes') .
                '` DROP FOREIGN KEY `meta_lead_notes_meta_leads`',
        );

        $this->dbforge->drop_table('meta_lead_notes');
    }
}
