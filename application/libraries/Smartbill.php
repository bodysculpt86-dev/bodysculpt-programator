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
 * Class Smartbill
 *
 * SmartBill invoicing API client (https://ws.smartbill.ro/SBORO/api).
 *
 * Authentication: HTTP Basic with the account email (SMARTBILL_USERNAME) and
 * the API token (SMARTBILL_TOKEN), both read via readEnvOrConfig() like the
 * other integrations. SMARTBILL_CIF is the issuing company fiscal code and
 * SMARTBILL_SERIES the invoice series (series must be created manually in the
 * SmartBill account before use).
 *
 * e-Factura: when the module is active on the SmartBill account, SmartBill
 * forwards issued invoices to ANAF SPV automatically - nothing to do here.
 */
class Smartbill
{
    /**
     * @var string SmartBill API base URL.
     */
    private const API_URL = 'https://ws.smartbill.ro/SBORO/api';

    /**
     * @var string|null Account email.
     */
    protected ?string $username = null;

    /**
     * @var string|null API token.
     */
    protected ?string $token = null;

    /**
     * @var string|null Issuing company fiscal code (CIF).
     */
    protected ?string $companyVatCode = null;

    /**
     * @var string|null Invoice series name.
     */
    protected ?string $series = null;

    /**
     * @var string|null Optional receipt (chitanta) series; SmartBill uses the
     * account's default receipt series when this is not set.
     */
    protected ?string $receiptSeries = null;

    /**
     * Smartbill constructor.
     */
    public function __construct()
    {
        $this->username = $this->readEnvOrConfig('SMARTBILL_USERNAME');
        $this->token = $this->readEnvOrConfig('SMARTBILL_TOKEN');
        $this->companyVatCode = $this->readEnvOrConfig('SMARTBILL_CIF');
        $this->series = $this->readEnvOrConfig('SMARTBILL_SERIES');
        $this->receiptSeries = $this->readEnvOrConfig('SMARTBILL_RECEIPT_SERIES');
    }

    /**
     * Whether all required settings are present.
     *
     * @return bool
     */
    public function is_configured(): bool
    {
        return !empty($this->username) && !empty($this->token) && !empty($this->companyVatCode) && !empty($this->series);
    }

    /**
     * Build the SmartBill invoice payload from our billing client + lines.
     *
     * @param array $client Billing client row (ea_billing_clients).
     * @param array $lines Invoice lines (description, qty, unit_price, vat_rate, source_type).
     * @param array $options issue_date (Y-m-d), is_draft (bool).
     *
     * @return array
     */
    public function build_invoice_payload(array $client, array $lines, array $options): array
    {
        $isCompany = ($client['type'] ?? 'pf') === 'pj';

        $payloadClient = [
            'name' => $client['name'],
            'address' => (string) ($client['address'] ?? ''),
            'city' => (string) ($client['city'] ?? ''),
            'county' => (string) ($client['county'] ?? ''),
            'country' => 'Romania',
            'email' => (string) ($client['email'] ?? ''),
            'isTaxPayer' => false,
            'saveToDb' => false,
        ];

        if ($isCompany) {
            // PJ: send the fiscal code and trade register number.
            $payloadClient['vatCode'] = 'RO' . preg_replace('/\D+/', '', (string) ($client['cui'] ?? ''));
            $payloadClient['regCom'] = (string) ($client['reg_com'] ?? '');
        }

        $products = [];

        foreach ($lines as $line) {
            $vatRate = (float) $line['vat_rate'];

            $products[] = [
                'name' => $line['description'],
                'measuringUnitName' => 'buc',
                'currency' => 'RON',
                'quantity' => (float) $line['qty'],
                'price' => (float) $line['unit_price'],
                'isTaxIncluded' => false,
                'taxName' => $this->mapTaxName($vatRate),
                'taxPercentage' => $vatRate,
                'isService' => ($line['source_type'] ?? 'manual') !== 'product',
                'saveToDb' => false,
            ];
        }

        $payload = [
            'companyVatCode' => $this->companyVatCode,
            'client' => $payloadClient,
            'issueDate' => $options['issue_date'],
            'seriesName' => $this->series,
            'isDraft' => (bool) ($options['is_draft'] ?? false),
            'currency' => 'RON',
            'language' => 'RO',
            'precision' => 2,
            'products' => $products,
        ];

        // Record the incasare on the invoice (real emissions only - never on
        // drafts) so SmartBill auto-generates the fiscal receipt document:
        //  - cash     => Chitanta (isCash: true, goes to Registrul de casa)
        //  - card     => Card (marks the invoice paid; the fiscal document is
        //                the POS receipt, NOT a chitanta)
        //  - transfer => no payment object: the invoice stays unpaid until the
        //                bank transfer is confirmed (fiscally correct)
        $payment_method = $options['payment_method'] ?? '';

        if (empty($options['is_draft']) && $payment_method === 'cash') {
            $payload['payment'] = [
                'value' => (float) ($options['total'] ?? 0),
                'type' => 'Chitanta',
                'isCash' => true,
            ];

            if (!empty($this->receiptSeries)) {
                $payload['payment']['paymentSeries'] = $this->receiptSeries;
            }
        } elseif (empty($options['is_draft']) && $payment_method === 'card') {
            $payload['payment'] = [
                'value' => (float) ($options['total'] ?? 0),
                'type' => 'Card',
                'isCash' => false,
            ];
        }

        return $payload;
    }

    /**
     * Create an invoice in SmartBill.
     *
     * @param array $payload Payload built by build_invoice_payload().
     *
     * @return array Result array: ['success' => bool, 'error' => string|null,
     *               'series' => string|null, 'number' => string|null, 'message' => string|null]
     */
    public function create_invoice(array $payload): array
    {
        try {
            $decoded = $this->request('POST', '/invoice', $payload);

            $errorText = trim((string) ($decoded['errorText'] ?? ''));

            if ($errorText !== '') {
                throw new Exception($errorText);
            }

            return [
                'success' => true,
                'error' => null,
                'series' => $decoded['series'] ?? null,
                // Zero-padded string ("0044") - stored verbatim, never cast.
                'number' => isset($decoded['number']) ? (string) $decoded['number'] : null,
                'message' => $decoded['message'] ?? null,
            ];
        } catch (Throwable $e) {
            log_message('error', '[smartbill] Invoice creation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'series' => null,
                'number' => null,
                'message' => null,
            ];
        }
    }

    /**
     * Fetch the PDF of an issued invoice.
     *
     * @param string $series Invoice series.
     * @param string $number Invoice number (zero-padded, verbatim).
     *
     * @return string|null Raw PDF bytes, or null on failure.
     */
    public function get_invoice_pdf(string $series, string $number): ?string
    {
        try {
            $query = http_build_query([
                'cif' => $this->companyVatCode,
                'seriesname' => $series,
                'number' => $number,
            ]);

            $ch = curl_init(self::API_URL . '/invoice/pdf?' . $query);

            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->token);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($response === false || $httpCode < 200 || $httpCode >= 300) {
                throw new Exception('SmartBill PDF error (HTTP ' . $httpCode . '): ' . curl_error($ch));
            }

            return $response;
        } catch (Throwable $e) {
            log_message('error', '[smartbill] PDF fetch failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Perform an authenticated JSON request against the SmartBill API.
     *
     * @param string $method HTTP method.
     * @param string $path API path (e.g. /invoice).
     * @param array|null $payload JSON body.
     *
     * @return array Decoded JSON response.
     *
     * @throws Exception On transport/HTTP/JSON errors.
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $ch = curl_init(self::API_URL . $path);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false) {
            throw new Exception('cURL error: ' . $curlError);
        }

        $decoded = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
            throw new Exception('SmartBill API error (HTTP ' . $httpCode . '): ' . substr((string) $response, 0, 300));
        }

        return $decoded;
    }

    /**
     * Map a VAT rate to the SmartBill tax name.
     *
     * @param float $vatRate
     *
     * @return string
     */
    private function mapTaxName(float $vatRate): string
    {
        if ($vatRate <= 0.0) {
            return 'Scutit';
        }

        if ($vatRate < 19.0) {
            return 'Redusa';
        }

        return 'Normala';
    }

    /**
     * Read a value from an environment variable or from the Config class.
     *
     * Mirrors Whatsapp_flaxxa::readEnvOrConfig().
     *
     * @param string $name The environment variable / Config constant name.
     *
     * @return string|null
     */
    private function readEnvOrConfig(string $name): ?string
    {
        $value = getenv($name);

        if ($value !== false && $value !== '') {
            return $value;
        }

        if (defined("Config::$name")) {
            $value = constant("Config::$name");
            return $value !== '' ? (string) $value : null;
        }

        return null;
    }
}
