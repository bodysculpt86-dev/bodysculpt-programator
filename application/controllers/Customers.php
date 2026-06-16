<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

/**
 * Customers controller.
 *
 * Handles the customers related operations.
 *
 * @package Controllers
 */

require_once APPPATH . 'libraries/Spreadsheet/SimpleXLS.php';
require_once APPPATH . 'libraries/Spreadsheet/SimpleXLSX.php';
require_once APPPATH . 'libraries/Spreadsheet/SimpleXLSXGen.php';

use Shuchkin\SimpleXLS;
use Shuchkin\SimpleXLSX;
use Shuchkin\SimpleXLSXGen;

class Customers extends EA_Controller
{
    public array $allowed_customer_fields = [
        'id',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'address',
        'city',
        'state',
        'zip_code',
        'notes',
        'language',
        'custom_field_1',
        'custom_field_2',
        'custom_field_3',
        'custom_field_4',
        'custom_field_5',
        'ldap_dn',
    ];

    public array $optional_customer_fields = [
        //
    ];

    /**
     * Customers constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('customers_model');
        $this->load->model('secretaries_model');
        $this->load->model('roles_model');

        $this->load->library('accounts');
        $this->load->library('permissions');
        $this->load->library('timezones');
        $this->load->library('webhooks_client');
    }

    /**
     * Render the backend customers page.
     *
     * On this page admin users will be able to manage customers, which are eventually selected by customers during the
     * booking process.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('customers')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_CUSTOMERS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');

        $date_format = setting('date_format');
        $time_format = setting('time_format');
        $require_first_name = setting('require_first_name');
        $require_last_name = setting('require_last_name');
        $require_email = setting('require_email');
        $require_phone_number = setting('require_phone_number');
        $require_address = setting('require_address');
        $require_city = setting('require_city');
        $require_zip_code = setting('require_zip_code');

        $secretary_providers = [];

        if ($role_slug === DB_SLUG_SECRETARY) {
            $secretary = $this->secretaries_model->find($user_id);

            $secretary_providers = $secretary['providers'];
        }

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'date_format' => $date_format,
            'time_format' => $time_format,
            'timezones' => $this->timezones->to_array(),
            'secretary_providers' => $secretary_providers,
            'default_language' => setting('default_language'),
            'default_timezone' => setting('default_timezone'),
        ]);

        html_vars([
            'page_title' => lang('customers'),
            'active_menu' => PRIV_CUSTOMERS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'timezones' => $this->timezones->to_array(),
            'privileges' => $this->roles_model->get_permissions_by_slug($role_slug),
            'require_first_name' => $require_first_name,
            'require_last_name' => $require_last_name,
            'require_email' => $require_email,
            'require_phone_number' => $require_phone_number,
            'require_address' => $require_address,
            'require_city' => $require_city,
            'require_zip_code' => $require_zip_code,
            'available_languages' => config('available_languages'),
        ]);

        $this->load->view('pages/customers');
    }

    /**
     * Find a customer.
     */
    public function find(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_CUSTOMERS)) {
                abort(403, 'Forbidden');
            }

            $user_id = session('user_id');

            check('customer_id', 'numeric');

            $customer_id = request('customer_id');

            // Validate customer_id is a positive integer
            if (empty($customer_id) || !filter_var($customer_id, FILTER_VALIDATE_INT) || $customer_id <= 0) {
                throw new InvalidArgumentException('Invalid customer ID provided.');
            }

            if (!$this->permissions->has_customer_access($user_id, $customer_id)) {
                abort(403, 'Forbidden');
            }

            $customer = $this->customers_model->find($customer_id);

            json_response($customer);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Filter customers by the provided keyword.
     */
    public function search(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_CUSTOMERS)) {
                abort(403, 'Forbidden');
            }

            check('keyword', 'string|null');
            check('order_by', 'string|null');
            check('limit', 'numeric|null');
            check('offset', 'numeric|null');

            $keyword = request('keyword', '');

            $order_by = request('order_by', 'update_datetime DESC');

            $limit = request('limit', 1000);

            $offset = (int) request('offset', '0');

            $customers = $this->customers_model->search($keyword, $limit, $offset, $order_by);

            $user_id = session('user_id');
            $role_slug = session('role_slug');

            $secretary_provider_ids = [];

            if ($role_slug === DB_SLUG_SECRETARY) {
                $secretary_provider_ids = $this->secretaries_model->find($user_id)['providers'];
            }

            foreach ($customers as $index => &$customer) {
                if (!$this->permissions->has_customer_access($user_id, $customer['id'])) {
                    unset($customers[$index]);

                    continue;
                }

                $appointments = $this->appointments_model->get(['id_users_customer' => $customer['id']]);

                // If the current user is a provider, only include their own appointments.
                if ($role_slug === DB_SLUG_PROVIDER) {
                    $appointments = array_filter($appointments, function ($appointment) use ($user_id) {
                        return (int) $appointment['id_users_provider'] === (int) $user_id;
                    });

                    $appointments = array_values($appointments);
                }

                // If the current user is a secretary, only include appointments of their providers.
                if ($role_slug === DB_SLUG_SECRETARY) {
                    $appointments = array_filter($appointments, function ($appointment) use ($secretary_provider_ids) {
                        return in_array((int) $appointment['id_users_provider'], $secretary_provider_ids);
                    });

                    $appointments = array_values($appointments);
                }

                foreach ($appointments as &$appointment) {
                    $this->appointments_model->load($appointment, ['service', 'provider']);
                }

                $customer['appointments'] = $appointments;
            }

            json_response(array_values($customers));
        } catch (Throwable $e) {
            json_exception($e);
        }
    }


    /**
     * Store a new customer.
     */
    public function store(): void
    {
        try {
            method('post');

            if (cannot('add', PRIV_CUSTOMERS)) {
                abort(403, 'Forbidden');
            }

            if (session('role_slug') !== DB_SLUG_ADMIN && setting('limit_customer_visibility')) {
                abort(403);
            }

            check('customer', 'array');

            $customer = request('customer');

            $this->customers_model->only($customer, $this->allowed_customer_fields);

            $this->customers_model->optional($customer, $this->optional_customer_fields);

            $customer['timezone'] = setting('default_timezone');

            $customer_id = $this->customers_model->save($customer);

            $customer = $this->customers_model->find($customer_id);

            $this->webhooks_client->trigger(WEBHOOK_CUSTOMER_SAVE, $customer);

            json_response([
                'success' => true,
                'id' => $customer_id,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Update a customer.
     */
    public function update(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_CUSTOMERS)) {
                abort(403, 'Forbidden');
            }

            $user_id = session('user_id');

            check('customer', 'array');

            $customer = request('customer');

            if (!$this->permissions->has_customer_access($user_id, $customer['id'])) {
                abort(403, 'Forbidden');
            }

            $this->customers_model->only($customer, $this->allowed_customer_fields);

            $this->customers_model->optional($customer, $this->optional_customer_fields);

            $customer['timezone'] = setting('default_timezone');

            $customer_id = $this->customers_model->save($customer);

            $customer = $this->customers_model->find($customer_id);

            $this->webhooks_client->trigger(WEBHOOK_CUSTOMER_SAVE, $customer);

            json_response([
                'success' => true,
                'id' => $customer_id,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Remove a customer.
     */
    public function destroy(): void
    {
        try {
            method('post');

            if (cannot('delete', PRIV_CUSTOMERS)) {
                abort(403, 'Forbidden');
            }

            $user_id = session('user_id');

            check('customer_id', 'numeric');

            $customer_id = request('customer_id');

            // Validate customer_id is a positive integer
            if (empty($customer_id) || !filter_var($customer_id, FILTER_VALIDATE_INT) || $customer_id <= 0) {
                throw new InvalidArgumentException('Invalid customer ID provided.');
            }

            if (!$this->permissions->has_customer_access($user_id, $customer_id)) {
                abort(403, 'Forbidden');
            }

            $customer = $this->customers_model->find($customer_id);

            $this->customers_model->delete($customer_id);

            $this->webhooks_client->trigger(WEBHOOK_CUSTOMER_DELETE, $customer);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Import customers from an uploaded XLS/XLSX file.
     */
    public function import(): void
    {
        try {
            method('post');

            if (cannot('add', PRIV_CUSTOMERS)) {
                abort(403, 'Forbidden');
            }

            if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('No file uploaded.');
            }

            $uploadedFile = $_FILES['import_file'];

            $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, ['xls', 'xlsx'], true)) {
                throw new InvalidArgumentException('Only .xls and .xlsx files are allowed.');
            }

            $uploadDir = FCPATH . 'storage/uploads/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filePath = $uploadDir . 'customers_import_' . time() . '.' . $extension;

            if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
                throw new RuntimeException('Could not save uploaded file.');
            }

            $rows = $this->readSpreadsheetRows($filePath, $extension);

            if (empty($rows)) {
                throw new InvalidArgumentException('The uploaded file is empty or could not be read.');
            }

            $headerMap = $this->buildImportHeaderMap($rows[0]);

            if (empty($headerMap['prenume']) || empty($headerMap['nume'])) {
                throw new InvalidArgumentException('The file must contain at least Prenume and Nume columns.');
            }

            $imported = 0;
            $failed = 0;
            $errors = [];

            $defaultLanguage = setting('default_language');
            $defaultTimezone = setting('default_timezone');

            for ($rowIndex = 1; $rowIndex < count($rows); $rowIndex++) {
                $row = $rows[$rowIndex];

                $firstName = $this->getCellValue($row, $headerMap['prenume'] ?? null);
                $lastName = $this->getCellValue($row, $headerMap['nume'] ?? null);

                if ($firstName === '' && $lastName === '') {
                    continue; // Skip empty rows.
                }

                $email = $this->getCellValue($row, $headerMap['email'] ?? null);
                $phone = $this->getCellValue($row, $headerMap['telefon'] ?? null);

                $customer = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone_number' => $phone,
                    'language' => $defaultLanguage,
                    'timezone' => $defaultTimezone,
                    'custom_field_1' => $this->getCellValue($row, $headerMap['idclient'] ?? null),
                    'custom_field_2' => $this->getCellValue($row, $headerMap['sex'] ?? null),
                    'custom_field_3' => $this->getCellValue($row, $headerMap['datanasterii'] ?? null),
                    'custom_field_4' => $this->getCellValue($row, $headerMap['tipclient'] ?? null),
                    'custom_field_5' => $this->getCellValue($row, $headerMap['grupclient'] ?? null),
                ];

                $gdpr = $this->getCellValue($row, $headerMap['acordgdpr'] ?? null);
                $marketing = $this->getCellValue($row, $headerMap['acordmarketing'] ?? null);

                $notes = [];

                if ($gdpr !== '') {
                    $notes[] = 'Acord GDPR: ' . $gdpr;
                }

                if ($marketing !== '') {
                    $notes[] = 'Acord Marketing: ' . $marketing;
                }

                $customer['notes'] = implode('; ', $notes);

                try {
                    $this->customers_model->only($customer, $this->allowed_customer_fields);
                    $this->customers_model->optional($customer, $this->optional_customer_fields);
                    $this->customers_model->save($customer);
                    $imported++;
                } catch (Throwable $e) {
                    $failed++;
                    $errors[] = 'Row ' . ($rowIndex + 1) . ': ' . $e->getMessage();

                    if (count($errors) >= 10) {
                        break;
                    }
                }
            }

            @unlink($filePath);

            json_response([
                'success' => true,
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Export all customers to an XLSX file.
     */
    public function export(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_CUSTOMERS)) {
                abort(403, 'Forbidden');
            }

            $customers = $this->customers_model->get();

            $rows = [
                [
                    'IdClient',
                    'Prenume',
                    'Nume',
                    'Sex',
                    'Telefon',
                    'Email',
                    'Data Nasterii',
                    'Data adaugare',
                    'Tip client',
                    'Grup client',
                    'Acord GDPR',
                    'Acord Marketing',
                ],
            ];

            foreach ($customers as $customer) {
                $notes = $customer['notes'] ?? '';

                $rows[] = [
                    ($customer['custom_field_1'] ? "\0" . $customer['custom_field_1'] : ''),
                    $customer['first_name'],
                    $customer['last_name'],
                    $customer['custom_field_2'] ?? '',
                    ($customer['phone_number'] ? "\0" . $customer['phone_number'] : ''),
                    $customer['email'] ?? '',
                    $customer['custom_field_3'] ?? '',
                    $customer['create_datetime'] ?? '',
                    $customer['custom_field_4'] ?? '',
                    $customer['custom_field_5'] ?? '',
                    $this->parseNoteValue($notes, 'Acord GDPR'),
                    $this->parseNoteValue($notes, 'Acord Marketing'),
                ];
            }

            $filename = 'customers_export_' . date('Ymd_His') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $xlsx = SimpleXLSXGen::fromArray($rows);
            $xlsx->downloadAs($filename);
            exit;
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Download an empty import template.
     */
    public function import_template(): void
    {
        try {
            method('get');

            if (cannot('add', PRIV_CUSTOMERS)) {
                abort(403, 'Forbidden');
            }

            $rows = [
                [
                    'IdClient',
                    'Prenume',
                    'Nume',
                    'Sex',
                    'Telefon',
                    'Email',
                    'Data Nasterii',
                    'Data adaugare',
                    'Tip client',
                    'Grup client',
                    'Acord GDPR',
                    'Acord Marketing',
                ],
                [
                    "\0" . '1',
                    'Ion',
                    'Popescu',
                    'M',
                    "\0" . '0712345678',
                    'ion.popescu@example.com',
                    '15.03.1990',
                    '',
                    'VIP',
                    'Grup 1',
                    'da',
                    'nu',
                ],
            ];

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="customers_import_template.xlsx"');
            header('Cache-Control: max-age=0');

            $xlsx = SimpleXLSXGen::fromArray($rows);
            $xlsx->downloadAs('customers_import_template.xlsx');
            exit;
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Read rows from an XLS or XLSX file.
     */
    private function readSpreadsheetRows(string $filePath, string $extension): array
    {
        if ($extension === 'xlsx') {
            $xlsx = SimpleXLSX::parse($filePath);

            if ($xlsx === false) {
                throw new RuntimeException('Could not read the XLSX file: ' . SimpleXLSX::parseError());
            }

            return $xlsx->rows();
        }

        $xls = SimpleXLS::parse($filePath);

        if ($xls === false) {
            throw new RuntimeException('Could not read the XLS file: ' . SimpleXLS::parseError());
        }

        return $xls->rows();
    }

    /**
     * Build a mapping between normalized column headers and column indexes.
     */
    private function buildImportHeaderMap(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $value) {
            switch ($this->normalizeHeader((string) $value)) {
                case 'idclient':
                    $map['idclient'] = $index;
                    break;
                case 'prenume':
                    $map['prenume'] = $index;
                    break;
                case 'nume':
                    $map['nume'] = $index;
                    break;
                case 'sex':
                    $map['sex'] = $index;
                    break;
                case 'telefon':
                    $map['telefon'] = $index;
                    break;
                case 'email':
                    $map['email'] = $index;
                    break;
                case 'datanasterii':
                    $map['datanasterii'] = $index;
                    break;
                case 'dataadaugare':
                    $map['dataadaugare'] = $index;
                    break;
                case 'tipclient':
                    $map['tipclient'] = $index;
                    break;
                case 'grupclient':
                    $map['grupclient'] = $index;
                    break;
                case 'acordgdpr':
                    $map['acordgdpr'] = $index;
                    break;
                case 'acordmarketing':
                    $map['acordmarketing'] = $index;
                    break;
            }
        }

        return $map;
    }

    /**
     * Normalize a header string for matching.
     */
    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        $value = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', ' ', '_', '-'],
            ['a', 'a', 'i', 's', 't', '', '', ''],
            $value,
        );

        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    }

    /**
     * Read a trimmed string value from a spreadsheet row.
     */
    private function getCellValue(array $row, ?int $column): string
    {
        if ($column === null || !array_key_exists($column, $row)) {
            return '';
        }

        $value = $row[$column];

        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * Parse a value stored in the notes field (e.g. "Acord GDPR: da").
     */
    private function parseNoteValue(string $notes, string $key): string
    {
        if (preg_match('/' . preg_quote($key, '/') . ':\s*([^;]+)/i', $notes, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }
}
