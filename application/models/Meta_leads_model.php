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
 * Meta leads model.
 *
 * Handles the meta_leads table: leads received from Meta Lead Ads via the
 * leadgen webhook, and their lifecycle (new -> converted) once a staff member
 * imports a lead when creating an appointment.
 */
class Meta_leads_model extends EA_Model
{
    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'customer_id' => 'integer',
        'capi_lead_event_sent' => 'boolean',
        'capi_converted_event_sent' => 'boolean',
    ];

    /**
     * Save (insert or update) a meta lead.
     *
     * @param array $lead Associative array with the lead data.
     *
     * @return int Returns the lead ID.
     */
    public function save(array $lead): int
    {
        $now = date('Y-m-d H:i:s');

        if (empty($lead['id'])) {
            unset($lead['id']);

            if (empty($lead['received_at'])) {
                $lead['received_at'] = $now;
            }

            $lead['create_datetime'] = $now;
            $lead['update_datetime'] = $now;

            $this->db->insert('meta_leads', $lead);

            return (int) $this->db->insert_id();
        }

        $lead['update_datetime'] = $now;

        $this->db->update('meta_leads', $lead, ['id' => (int) $lead['id']]);

        return (int) $lead['id'];
    }

    /**
     * Find a meta lead by its database ID.
     *
     * @param int $lead_id
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function find(int $lead_id): array
    {
        $lead = $this->db->get_where('meta_leads', ['id' => $lead_id])->row_array();

        if (!$lead) {
            throw new InvalidArgumentException('The provided meta lead ID was not found in the database: ' . $lead_id);
        }

        $this->cast($lead);

        return $lead;
    }

    /**
     * Find a meta lead by its Meta leadgen ID.
     *
     * @param string $leadgen_id
     *
     * @return array|null
     */
    public function find_by_leadgen_id(string $leadgen_id): ?array
    {
        $lead = $this->db->get_where('meta_leads', ['leadgen_id' => $leadgen_id])->row_array();

        if (!$lead) {
            return null;
        }

        $this->cast($lead);

        return $lead;
    }

    /**
     * Search meta leads by keyword and optional status.
     *
     * @param string $keyword
     * @param string|null $status Only 'new' or 'converted', null for all.
     * @param int $limit
     * @param int $offset
     *
     * @return array
     */
    public function search(string $keyword = '', ?string $status = null, int $limit = 20, int $offset = 0): array
    {
        $this->db->from('meta_leads');

        if ($keyword !== '') {
            $this->db
                ->group_start()
                ->like('first_name', $keyword)
                ->or_like('last_name', $keyword)
                ->or_like('CONCAT_WS(" ", first_name, last_name)', $keyword)
                ->or_like('email', $keyword)
                ->or_like('phone_number', $keyword)
                ->group_end();
        }

        if ($status !== null && in_array($status, ['new', 'converted'], true)) {
            $this->db->where('status', $status);
        }

        return $this->db
            ->order_by('received_at', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
    }

    /**
     * Mark a lead as converted and link it to the created/reused customer.
     *
     * @param int $lead_id
     * @param int $customer_id
     *
     * @return void
     */
    public function mark_converted(int $lead_id, int $customer_id): void
    {
        $this->db->where('id', $lead_id)->update('meta_leads', [
            'status' => 'converted',
            'converted_at' => date('Y-m-d H:i:s'),
            'customer_id' => $customer_id,
            'update_datetime' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Record that a Conversions API stage event was successfully sent.
     *
     * @param int $lead_id
     * @param string $stage 'crm_lead' or 'converted'.
     *
     * @return void
     */
    public function mark_capi_event_sent(int $lead_id, string $stage): void
    {
        $column = $stage === 'converted' ? 'capi_converted_event_sent' : 'capi_lead_event_sent';

        $this->db->where('id', $lead_id)->update('meta_leads', [
            $column => 1,
            'update_datetime' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete a meta lead.
     *
     * @param int $lead_id
     *
     * @return void
     */
    public function delete(int $lead_id): void
    {
        $this->db->delete('meta_leads', ['id' => $lead_id]);
    }
}
