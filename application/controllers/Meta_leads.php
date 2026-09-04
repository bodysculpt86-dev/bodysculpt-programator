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

        html_vars([
            'page_title' => lang('meta_leads'),
            'active_menu' => 'meta_leads',
            'user_display_name' => $this->accounts->get_user_display_name(session('user_id')),
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
