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
 * Invoices model.
 *
 * Fiscal invoices (ea_invoices) and their lines (ea_invoice_items), issued
 * via SmartBill from the standalone invoicing page.
 */
class Invoices_model extends EA_Model
{
    /**
     * Find an invoice by its idempotency key.
     *
     * @param string $idempotencyKey
     *
     * @return array|null
     */
    public function find_by_idempotency_key(string $idempotencyKey): ?array
    {
        if ($idempotencyKey === '') {
            return null;
        }

        $invoice = $this->db->get_where('invoices', ['idempotency_key' => $idempotencyKey])->row_array();

        return $invoice ?: null;
    }

    /**
     * Get a specific invoice from the database.
     *
     * @param int $invoice_id
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function find(int $invoice_id): array
    {
        $invoice = $this->db->get_where('invoices', ['id' => $invoice_id])->row_array();

        if (!$invoice) {
            throw new InvalidArgumentException('The provided invoice ID was not found in the database: ' . $invoice_id);
        }

        return $invoice;
    }

    /**
     * Create an invoice row (status "pending") together with its lines.
     *
     * @param array $invoice Invoice fields (billing_client_id, issue_date, payment_method,
     *                       subtotal, vat_total, total, is_draft, idempotency_key, created_by).
     * @param array $items Line rows (source_type, source_id, description, qty, unit_price,
     *                     vat_rate, line_total).
     *
     * @return int The new invoice ID.
     */
    public function create_pending(array $invoice, array $items): int
    {
        $invoice['smartbill_status'] = 'pending';
        $invoice['created_at'] = date('Y-m-d H:i:s');

        $this->db->trans_start();

        $this->db->insert('invoices', $invoice);

        $invoice_id = (int) $this->db->insert_id();

        foreach ($items as $item) {
            $item['invoice_id'] = $invoice_id;

            $this->db->insert('invoice_items', $item);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            throw new RuntimeException('Could not create the invoice in the database.');
        }

        return $invoice_id;
    }

    /**
     * Mark an invoice as successfully issued in SmartBill.
     *
     * @param int $invoice_id
     * @param string|null $series
     * @param string|null $number Zero-padded, stored verbatim.
     * @param string|null $message
     */
    public function mark_issued(int $invoice_id, ?string $series, ?string $number, ?string $message = null): void
    {
        $this->db->update(
            'invoices',
            [
                'smartbill_status' => 'issued',
                'series' => $series,
                'number' => $number,
                'smartbill_message' => $message,
            ],
            ['id' => $invoice_id],
        );
    }

    /**
     * Mark an invoice emission as failed (keeps the row for review/retry).
     *
     * @param int $invoice_id
     * @param string $message
     */
    public function mark_failed(int $invoice_id, string $message): void
    {
        $this->db->update(
            'invoices',
            [
                'smartbill_status' => 'failed',
                'smartbill_message' => substr($message, 0, 512),
            ],
            ['id' => $invoice_id],
        );
    }

    /**
     * List invoices for the history table (joined with the billing client).
     *
     * @param int $limit
     *
     * @return array
     */
    public function get_history(int $limit = 100): array
    {
        return $this->db
            ->select(
                'invoices.id, invoices.issue_date, invoices.created_at, invoices.series, invoices.number, '
                . 'invoices.total, invoices.smartbill_status, invoices.is_draft, '
                . 'billing_clients.name AS client_name, billing_clients.type AS client_type',
            )
            ->from('invoices')
            ->join('billing_clients', 'billing_clients.id = invoices.billing_client_id', 'left')
            ->order_by('invoices.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    /**
     * Get the lines of an invoice.
     *
     * @param int $invoice_id
     *
     * @return array
     */
    public function get_items(int $invoice_id): array
    {
        return $this->db->get_where('invoice_items', ['invoice_id' => $invoice_id])->result_array();
    }
}
