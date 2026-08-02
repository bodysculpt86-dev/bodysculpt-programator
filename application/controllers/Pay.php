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
 * Pay controller.
 *
 * Public short payment link redirect endpoint.
 *
 * GET /pay/<slug> → 302 redirect to the original Stripe Checkout URL.
 *
 * Intentionally PUBLIC (no session guard): customers open the short link
 * from WhatsApp on any device. Missing or expired slugs return 404.
 */
class Pay extends EA_Controller
{
    /**
     * Pay constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('payment_links_model');
    }

    /**
     * Redirect a short payment link to its Stripe Checkout URL.
     *
     * @param string $slug Short payment link slug.
     */
    public function index(string $slug = ''): void
    {
        method('get');

        // Only accept well-formed slugs (alphanumeric, case-sensitive, max 16 chars).
        if (empty($slug) || !preg_match('/^[A-Za-z0-9]{1,16}$/', $slug)) {
            show_404();

            return;
        }

        $link = $this->payment_links_model->find_active_by_slug($slug);

        if (empty($link)) {
            show_404();

            return;
        }

        $this->payment_links_model->register_click((int) $link['id']);

        // 302 (temporary) redirect, never 301: the short URL must not be
        // cached permanently by browsers or intermediaries, and Stripe may
        // reject permanent redirects in some flows.
        redirect($link['stripe_url'], 'location', 302);
    }
}
