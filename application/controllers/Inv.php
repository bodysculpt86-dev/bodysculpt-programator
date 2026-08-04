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
 * Inv controller.
 *
 * Public short invoice PDF endpoint.
 *
 * GET /inv/<slug> → streams the SmartBill PDF of the issued invoice.
 *
 * Intentionally PUBLIC (no session guard): Meta/WhatsApp servers fetch the
 * document from this URL when a document-header template message is sent.
 * Missing or expired slugs return 404. Slugs are unguessable (62^8 space).
 */
class Inv extends EA_Controller
{
    /**
     * Inv constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('invoice_links_model');
        $this->load->model('invoices_model');

        $this->load->library('smartbill');
    }

    /**
     * Stream the invoice PDF for a short invoice link.
     *
     * @param string $slug
     */
    public function index(string $slug = ''): void
    {
        method('get');

        if (empty($slug) || !preg_match('/^[A-Za-z0-9]{1,16}$/', $slug)) {
            show_404();

            return;
        }

        $link = $this->invoice_links_model->find_active_by_slug($slug);

        if (empty($link)) {
            show_404();

            return;
        }

        $this->invoice_links_model->register_click((int) $link['id']);

        $invoice = $this->invoices_model->find((int) $link['id_invoices']);

        if ($invoice['smartbill_status'] !== 'issued' || empty($invoice['series']) || empty($invoice['number'])) {
            show_404();

            return;
        }

        $pdf = $this->smartbill->get_invoice_pdf($invoice['series'], $invoice['number']);

        if ($pdf === null) {
            show_404();

            return;
        }

        $this->output
            ->set_content_type('application/pdf')
            ->set_header(
                'Content-Disposition: inline; filename="factura-' . $invoice['series'] . $invoice['number'] . '.pdf"',
            )
            ->set_output($pdf);
    }
}
