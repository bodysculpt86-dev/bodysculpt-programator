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
 * Payment links model.
 *
 * Stores short internal payment links (/pay/<slug>) that redirect to the
 * original (long) Stripe Checkout URLs sent to customers via WhatsApp.
 */
class Payment_links_model extends EA_Model
{
    /**
     * Default link lifetime in hours (matches the 24h unpaid-deposit window).
     */
    protected const DEFAULT_TTL_HOURS = 24;

    /**
     * Create a new short payment link for a Stripe Checkout URL.
     *
     * Generates a unique, cryptographically secure slug and persists the
     * mapping. Expired rows are purged opportunistically on each create.
     *
     * @param string $stripe_url Original Stripe Checkout URL.
     * @param int|null $appointment_id Related appointment ID (optional).
     * @param int $ttl_hours Link lifetime in hours (default 24).
     *
     * @return string The generated slug.
     *
     * @throws RuntimeException If a unique slug cannot be generated.
     */
    public function create(string $stripe_url, ?int $appointment_id = null, int $ttl_hours = self::DEFAULT_TTL_HOURS): string
    {
        $this->load->helper('payment_link');

        $this->purge_expired(); // Housekeeping: drop links older than the retention window.

        $slug = null;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = generate_payment_slug(8);

            $exists = $this->db->get_where('payment_links', ['slug' => $candidate])->num_rows() > 0;

            if (!$exists) {
                $slug = $candidate;
                break;
            }
        }

        if ($slug === null) {
            throw new RuntimeException('Could not generate a unique payment link slug.');
        }

        $now = date('Y-m-d H:i:s');

        $this->db->insert('payment_links', [
            'slug' => $slug,
            'stripe_url' => $stripe_url,
            'id_appointments' => $appointment_id,
            'create_datetime' => $now,
            'expires_at' => date('Y-m-d H:i:s', strtotime($now . ' +' . $ttl_hours . ' hours')),
            'clicked_at' => null,
            'click_count' => 0,
        ]);

        return $slug;
    }

    /**
     * Find an active (existing, not expired) payment link by its slug.
     *
     * @param string $slug
     *
     * @return array|null The payment link row, or null if missing/expired.
     */
    public function find_active_by_slug(string $slug): ?array
    {
        $link = $this->db->get_where('payment_links', ['slug' => $slug])->row_array();

        if (empty($link)) {
            return null;
        }

        if (strtotime($link['expires_at']) <= time()) {
            return null;
        }

        return $link;
    }

    /**
     * Register a click on a payment link.
     *
     * clicked_at stores the FIRST access time; click_count increments on
     * every access.
     *
     * @param int $id Payment link ID.
     */
    public function register_click(int $id): void
    {
        $this->db->set('click_count', 'click_count + 1', false);
        $this->db->set('clicked_at', 'COALESCE(clicked_at, NOW())', false);
        $this->db->where('id', $id);
        $this->db->update('payment_links');
    }

    /**
     * Delete old payment links (housekeeping).
     *
     * Links stay in the database for $retention_days after creation so the
     * click history (clicked_at / click_count) remains available for a week,
     * then they are purged. Called on every create and hourly from the
     * process_unpaid_deposits cron.
     *
     * @param int $retention_days How many days to keep links (default 7).
     *
     * @return int Number of deleted rows.
     */
    public function purge_expired(int $retention_days = 7): int
    {
        $this->db->where('create_datetime <=', date('Y-m-d H:i:s', strtotime('-' . $retention_days . ' days')));
        $this->db->delete('payment_links');

        return $this->db->affected_rows();
    }
}
