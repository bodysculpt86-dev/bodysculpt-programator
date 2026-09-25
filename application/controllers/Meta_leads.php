<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Meta Leads admin section.
 * ---------------------------------------------------------------------------- */

/**
 * Meta_leads controller.
 *
 * Lists Meta Lead Ads leads received via the leadgen webhook, lets admins
 * inspect the raw form answers and delete leads. Converting a lead into a
 * customer happens in Calendar::save_appointment() when a lead is imported
 * while creating an appointment.
 *
 * @package Controllers
 */
class Meta_leads extends EA_Controller
{
    /**
     * Meta_leads constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('meta_leads_model');

        $this->load->library('accounts');
    }

    /**
     * Ensure the current user is an authenticated admin.
     *
     * @return bool
     */
    private function guard_admin(): bool
    {
        if (session('role_slug') !== DB_SLUG_ADMIN) {
            if (session('user_id')) {
                abort(403, 'Forbidden');
            } else {
                redirect('login');
            }

            return false;
        }

        return true;
    }

    /**
     * Render the Meta Leads page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('meta_leads')]);

        if (!$this->guard_admin()) {
            return;
        }

        // Start from the known form_id -> procedure mapping, then add any
        // form_id seen in the data that isn't mapped yet (labelled with its
        // raw form_id), so a brand-new form is filterable immediately,
        // before anyone adds it to META_LEAD_FORM_PROCEDURES. Built with a
        // loop rather than array_merge() because the mapping's numeric-string
        // keys are cast to int by PHP, and array_merge() would renumber them.
        $procedure_options = META_LEAD_FORM_PROCEDURES;

        foreach ($this->meta_leads_model->get_distinct_form_ids() as $form_id) {
            if (!isset($procedure_options[$form_id])) {
                $procedure_options[$form_id] = $form_id;
            }
        }

        html_vars([
            'page_title' => lang('meta_leads'),
            'active_menu' => 'meta_leads',
            'user_display_name' => $this->accounts->get_user_display_name(session('user_id')),
            'procedure_options' => $procedure_options,
        ]);

        $this->load->view('pages/meta_leads');
    }

    /**
     * Return a filtered list of leads (JSON).
     */
    public function search(): void
    {
        try {
            method('post');

            if (session('role_slug') !== DB_SLUG_ADMIN) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            $keyword = trim((string) request('keyword', ''));
            $status = request('status');

            if (!in_array($status, ['new', 'converted'], true)) {
                $status = null;
            }

            $limit = (int) request('limit', 20);
            $offset = (int) request('offset', 0);

            json_response(array_values($this->meta_leads_model->search($keyword, $status, $limit, $offset)));
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Return leads for the internal call workflow, filtered by call_status (JSON).
     */
    public function search_calls(): void
    {
        try {
            method('post');

            if (session('role_slug') !== DB_SLUG_ADMIN) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            $keyword = trim((string) request('keyword', ''));
            $call_status = request('call_status');

            $allowed = ['de sunat', 'nu a raspuns', 'revine', 'nu e interesat'];

            if (!in_array($call_status, $allowed, true)) {
                $call_status = null;
            }

            $limit = (int) request('limit', 200);
            $offset = (int) request('offset', 0);

            $form_id = trim((string) request('form_id', ''));
            $form_id = $form_id !== '' ? $form_id : null;

            json_response(
                array_values($this->meta_leads_model->search_calls($call_status, $keyword, $limit, $offset, $form_id)),
            );
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Update a lead's internal call workflow fields (call_status / call_note).
     *
     * The first call_status change auto-assigns the lead to the current user.
     */
    public function update_call(): void
    {
        try {
            method('post');

            if (session('role_slug') !== DB_SLUG_ADMIN) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('lead_id', 'numeric');

            $lead_id = (int) request('lead_id');
            $lead = $this->meta_leads_model->find($lead_id);

            $update = [];

            $call_status = request('call_status');

            if ($call_status !== null && $call_status !== '') {
                $allowed = ['de sunat', 'nu a raspuns', 'revine', 'nu e interesat'];

                if (!in_array($call_status, $allowed, true)) {
                    throw new InvalidArgumentException('Invalid call status.');
                }

                $update['call_status'] = $call_status;

                // Auto-assign the lead to the current user on the first change.
                if (empty($lead['assigned_to'])) {
                    $update['assigned_to'] = (int) session('user_id');
                }
            }

            $call_note = request('call_note');

            if ($call_note !== null) {
                $update['call_note'] = (string) $call_note;
            }

            if ($update !== []) {
                $this->meta_leads_model->update_call($lead_id, $update);
            }

            $updated = $this->meta_leads_model->find($lead_id);

            json_response(['success' => true, 'lead' => $updated]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Return a single lead with its raw form fields parsed (JSON).
     */
    public function show(): void
    {
        try {
            method('post');

            if (session('role_slug') !== DB_SLUG_ADMIN) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('lead_id', 'numeric');

            $lead = $this->meta_leads_model->find((int) request('lead_id'));

            $lead['form_fields'] = json_decode((string) ($lead['form_fields'] ?? '[]'), true) ?: [];

            json_response($lead);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Delete a lead (JSON).
     */
    public function destroy(): void
    {
        try {
            method('post');

            if (session('role_slug') !== DB_SLUG_ADMIN) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('lead_id', 'numeric');

            $this->meta_leads_model->delete((int) request('lead_id'));

            json_response(['success' => true]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
