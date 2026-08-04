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
 * Invoice links model.
 *
 * Short public links (/inv/<slug>) that stream an issued invoice's PDF,
 * used by the WhatsApp document template flow (Meta fetches the PDF at send
 * time). Mirrors Payment_links_model.
 */
class Invoice_links_model extends EA_Model
{
    /**
     * Default link lifetime in hours (Meta fetches the PDF at send time, so
     * links do not need to live long).
     */
    protected const DEFAULT_TTL_HOURS = 48;

    /**
     * Create a new short link for an invoice PDF.
     *
     * @param int $invoice_id
     * @param int $ttl_hours
     *
     * @return string The generated slug.
     *
     * @throws RuntimeException If a unique slug cannot be generated.
     */
    public function create(int $invoice_id, int $ttl_hours = self::DEFAULT_TTL_HOURS): string
    {
        $this->load->helper('payment_link');

        $slug = null;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = generate_payment_slug(8);

            $exists = $this->db->get_where('invoice_links', ['slug' => $candidate])->num_rows() > 0;

            if (!$exists) {
                $slug = $candidate;
                break;
            }
        }

        if ($slug === null) {
            throw new RuntimeException('Could not generate a unique invoice link slug.');
        }

        $now = date('Y-m-d H:i:s');

        $this->db->insert('invoice_links', [
            'slug' => $slug,
            'id_invoices' => $invoice_id,
            'create_datetime' => $now,
            'expires_at' => date('Y-m-d H:i:s', strtotime($now . ' +' . $ttl_hours . ' hours')),
            'clicked_at' => null,
            'click_count' => 0,
        ]);

        return $slug;
    }

    /**
     * Find an active (existing, not expired) invoice link by its slug.
     *
     * @param string $slug
     *
     * @return array|null
     */
    public function find_active_by_slug(string $slug): ?array
    {
        $link = $this->db->get_where('invoice_links', ['slug' => $slug])->row_array();

        if (empty($link)) {
            return null;
        }

        if (strtotime($link['expires_at']) <= time()) {
            return null;
        }

        return $link;
    }

    /**
     * Register a click on an invoice link (clicked_at = first access).
     *
     * @param int $id
     */
    public function register_click(int $id): void
    {
        $this->db->set('click_count', 'click_count + 1', false);
        $this->db->set('clicked_at', 'COALESCE(clicked_at, NOW())', false);
        $this->db->where('id', $id);
        $this->db->update('invoice_links');
    }
}
