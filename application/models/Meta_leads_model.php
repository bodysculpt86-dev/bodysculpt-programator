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
     * AI call classifications that represent a real, engaged conversation
     * (the lead answered and said something). Used by save_call_result() to
     * decide whether a later call is allowed to overwrite an earlier one.
     */
    private const PROTECTED_AI_CLASSIFICATIONS = ['HOT', 'WARM', 'COLD', 'CALLBACK'];

    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'customer_id' => 'integer',
        'assigned_to' => 'integer',
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
     * Find the most recently received meta lead by normalized phone number.
     *
     * Used as the identification fallback in Webhooks_autocalls when leadgen_id
     * isn't available or doesn't match any lead. Multiple leads can share a
     * phone number over time; the most recently received one is the one an AI
     * call result almost certainly belongs to.
     *
     * @param string $normalized_phone Digits from normalize_international_phone(), with or without a leading '+'.
     *
     * @return array|null
     */
    public function find_by_phone(string $normalized_phone): ?array
    {
        $phone = '+' . ltrim($normalized_phone, '+');

        $lead = $this->db
            ->where('phone_number', $phone)
            ->order_by('received_at', 'DESC')
            ->limit(1)
            ->get('meta_leads')
            ->row_array();

        if (!$lead) {
            return null;
        }

        $this->cast($lead);

        return $lead;
    }

    /**
     * Save the outcome of an AI call (from Autocalls) for a lead.
     *
     * Idempotent and order-aware:
     * - A call_at older than the one already stored is ignored entirely (an
     *   out-of-order or duplicate delivery changes nothing).
     * - A classification in PROTECTED_AI_CLASSIFICATIONS is never overwritten
     *   by a later call that only reaches NO_ANSWER/FAILED — a HOT/WARM/COLD/
     *   CALLBACK lead doesn't get silently demoted by a follow-up
     *   verification call nobody picked up. When that overwrite is blocked,
     *   only the attempt number and call time move; classification, summary,
     *   desired_procedure and recording_url are left as they were.
     *
     * @param array $lead Current lead row (from find()/find_by_leadgen_id()/find_by_phone()).
     * @param array $call {
     *     @var string $classification One of HOT|WARM|COLD|CALLBACK|NO_ANSWER|FAILED.
     *     @var string|null $summary
     *     @var string|null $desired_procedure
     *     @var int|null $attempt_number
     *     @var string $call_at Datetime (Y-m-d H:i:s).
     *     @var string|null $recording_url
     * }
     *
     * @return void
     */
    public function save_call_result(array $lead, array $call): void
    {
        $call_at = $call['call_at'];
        $stored_call_at = $lead['ai_call_at'] ?? null;

        if ($stored_call_at !== null && strtotime($call_at) < strtotime($stored_call_at)) {
            return;
        }

        $stored_is_protected = in_array($lead['ai_call_classification'] ?? null, self::PROTECTED_AI_CLASSIFICATIONS, true);
        $incoming_is_protected = in_array($call['classification'], self::PROTECTED_AI_CLASSIFICATIONS, true);

        $data = [
            'ai_call_attempt_number' => $call['attempt_number'] ?? null,
            'ai_call_at' => $call_at,
            'ai_call_updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!$stored_is_protected || $incoming_is_protected) {
            $data['ai_call_classification'] = $call['classification'];
            $data['ai_call_summary'] = $call['summary'] ?? null;
            $data['ai_call_desired_procedure'] = $call['desired_procedure'] ?? null;
            $data['ai_call_recording_url'] = $call['recording_url'] ?? null;
        }

        $this->db->where('id', $lead['id'])->update('meta_leads', $data);
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
     * Search meta leads for the internal call workflow, optionally filtered by
     * call_status, form_id and/or status. Each row is enriched with a
     * has_appointments count (whether the linked customer has any
     * appointment), the assigned user's name, and procedure (the
     * clinic-facing name for the lead's form_id, from
     * META_LEAD_FORM_PROCEDURES, falling back to the raw form_id when it
     * isn't in that mapping).
     *
     * The status filter is independent of call_status: it selects on the
     * lead's lifecycle column ('new'/'converted'), not the internal call
     * workflow column, so it can be combined with keyword/form_id or used on
     * its own for the "Programați" (scheduled) tab.
     *
     * Leads are ordered strictly newest-first (received_at DESC), with no
     * call_status prioritization.
     *
     * @param string|null $call_status One of the allowed call statuses, null for all.
     * @param string $keyword
     * @param int $limit
     * @param int $offset
     * @param string|null $form_id Only leads from this form_id, null for all.
     * @param string|null $status Only 'new' or 'converted', null for all.
     *
     * @return array
     */
    public function search_calls(
        ?string $call_status = null,
        string $keyword = '',
        int $limit = 200,
        int $offset = 0,
        ?string $form_id = null,
        ?string $status = null,
    ): array {
        $this->db
            ->select(
                "ml.*, " .
                    "COUNT(a.id) AS has_appointments, " .
                    "MAX(CONCAT_WS(' ', u.first_name, u.last_name)) AS assigned_to_name",
                false,
            )
            ->from('meta_leads ml')
            ->join('appointments a', 'a.id_users_customer = ml.customer_id AND a.is_unavailability = 0', 'left')
            ->join('users u', 'u.id = ml.assigned_to', 'left');

        if ($keyword !== '') {
            $this->db
                ->group_start()
                ->like('ml.first_name', $keyword)
                ->or_like('ml.last_name', $keyword)
                ->or_like('CONCAT_WS(" ", ml.first_name, ml.last_name)', $keyword, 'both', false)
                ->or_like('ml.email', $keyword)
                ->or_like('ml.phone_number', $keyword)
                ->group_end();
        }

        $allowed = ['de sunat', 'nu a raspuns', 'revine', 'nu e interesat'];

        if ($call_status !== null && in_array($call_status, $allowed, true)) {
            $this->db->where('ml.call_status', $call_status);
        }

        if ($form_id !== null && $form_id !== '') {
            $this->db->where('ml.form_id', $form_id);
        }

        if ($status !== null && in_array($status, ['new', 'converted'], true)) {
            $this->db->where('ml.status', $status);
        }

        $this->db->group_by('ml.id');
        $this->db->order_by('ml.received_at', 'DESC');

        $leads = $this->db->limit($limit, $offset)->get()->result_array();

        foreach ($leads as &$lead) {
            $lead['procedure'] = META_LEAD_FORM_PROCEDURES[$lead['form_id'] ?? ''] ?? (string) ($lead['form_id'] ?? '');
        }

        return $leads;
    }

    /**
     * Return every distinct non-empty form_id present in meta_leads.
     *
     * Used to populate the procedure filter with forms that have received
     * leads but aren't (yet) in META_LEAD_FORM_PROCEDURES, so a brand-new
     * form is filterable immediately, before anyone adds it to the mapping.
     *
     * @return string[]
     */
    public function get_distinct_form_ids(): array
    {
        $rows = $this->db
            ->select('form_id')
            ->distinct()
            ->from('meta_leads')
            ->where('form_id IS NOT NULL', null, false)
            ->where('form_id !=', '')
            ->get()
            ->result_array();

        return array_column($rows, 'form_id');
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
     * @param string $stage Column selector: 'converted' (capi_converted_event_sent) or 'crm_lead' (capi_lead_event_sent).
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
     * Update the internal call workflow fields of a meta lead.
     *
     * Only whitelisted fields are written. When call_status is present the
     * call_updated_at timestamp is refreshed.
     *
     * @param int $lead_id
     * @param array $data Fields to update (call_status, call_note, assigned_to).
     *
     * @return void
     */
    public function update_call(int $lead_id, array $data): void
    {
        $data = array_intersect_key($data, array_flip(['call_status', 'call_note', 'assigned_to']));

        if (isset($data['call_status'])) {
            $data['call_updated_at'] = date('Y-m-d H:i:s');
        }

        $data['update_datetime'] = date('Y-m-d H:i:s');

        $this->db->where('id', $lead_id)->update('meta_leads', $data);
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
