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
 * Billing clients model.
 *
 * Fiscal clients (persoana fizica / persoana juridica) used by the standalone
 * invoicing page. Separate from booking customers (ea_users) on purpose:
 * companies may never book an appointment but still need invoices.
 */
class Billing_clients_model extends EA_Model
{
    /**
     * Search billing clients by name or CUI.
     *
     * @param string $keyword
     * @param int $limit
     * @param int $offset
     *
     * @return array
     */
    public function search(string $keyword = '', int $limit = 20, int $offset = 0): array
    {
        if ($keyword !== '') {
            $this->db
                ->group_start()
                ->like('name', $keyword)
                ->or_like('cui', $keyword)
                ->group_end();
        }

        return $this->db
            ->from('billing_clients')
            ->order_by('name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
    }

    /**
     * Get a specific billing client from the database.
     *
     * @param int $client_id
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function find(int $client_id): array
    {
        $client = $this->db->get_where('billing_clients', ['id' => $client_id])->row_array();

        if (!$client) {
            throw new InvalidArgumentException(
                'The provided billing client ID was not found in the database: ' . $client_id,
            );
        }

        return $client;
    }

    /**
     * Save (insert/update) a billing client.
     *
     * @param array $client Whitelisted client fields (see Invoices::$allowed_client_fields).
     *
     * @return int The billing client ID.
     *
     * @throws InvalidArgumentException
     */
    public function save(array $client): int
    {
        $client['type'] = in_array($client['type'] ?? '', ['pf', 'pj'], true) ? $client['type'] : 'pf';

        if (trim((string) ($client['name'] ?? '')) === '') {
            throw new InvalidArgumentException('The billing client name is required.');
        }

        if ($client['type'] === 'pj' && trim((string) ($client['cui'] ?? '')) === '') {
            throw new InvalidArgumentException('CUI is required for persoana juridica billing clients.');
        }

        $now = date('Y-m-d H:i:s');

        if (empty($client['id'])) {
            unset($client['id']);

            $client['created_at'] = $now;
            $client['updated_at'] = $now;

            $this->db->insert('billing_clients', $client);

            return (int) $this->db->insert_id();
        }

        $client['updated_at'] = $now;

        $this->db->update('billing_clients', $client, ['id' => (int) $client['id']]);

        return (int) $client['id'];
    }
}
