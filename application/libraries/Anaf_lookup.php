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
 * Class Anaf_lookup
 *
 * Looks up a Romanian company by CUI in the official ANAF public web service
 * (PlatitorTvaRest v9, active since 2025-02-04; v8 expired 2025-05-01).
 *
 * The service is unauthenticated: POST a JSON array of
 * [{"cui": <number>, "data": "yyyy-MM-dd"}] and receive
 * {"found": [...], "notFound": [...]} (no cod/message envelope on live 200s).
 *
 * Never throws: all failure modes (invalid CUI, company not found, ANAF down)
 * return a clean result array the UI can present to the staff member.
 */
class Anaf_lookup
{
    /**
     * @var string ANAF PlatitorTvaRest v9 endpoint (confirmed current version).
     */
    private const API_URL = 'https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva';

    /**
     * Look up a company by CUI.
     *
     * @param string $cui Raw CUI input (may contain "RO" prefix, spaces, etc.).
     *
     * @return array Result array:
     *               ['success' => true, 'error' => null, 'client' => [
     *                   'cui', 'name', 'address', 'city', 'county', 'reg_com', 'vat_payer'
     *               ]]
     *               or ['success' => false, 'error' => 'invalid_cui'|'not_found'|'anaf_unavailable']
     */
    public function lookup(string $cui): array
    {
        $cui_digits = preg_replace('/\D+/', '', $cui);

        if ($cui_digits === '' || strlen($cui_digits) > 10) {
            return ['success' => false, 'error' => 'invalid_cui'];
        }

        try {
            $payload = [
                [
                    'cui' => (int) $cui_digits,
                    'data' => date('Y-m-d'),
                ],
            ];

            $ch = curl_init(self::API_URL);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            if ($response === false) {
                throw new Exception('cURL error: ' . $curlError);
            }

            $decoded = json_decode($response, true);

            if (!is_array($decoded) || $httpCode < 200 || $httpCode >= 300) {
                throw new Exception('ANAF API error (HTTP ' . $httpCode . ')');
            }

            if (!empty($decoded['notFound'])) {
                return ['success' => false, 'error' => 'not_found'];
            }

            $general = $decoded['found'][0]['date_generale'] ?? null;

            if (empty($general) || empty($general['denumire'])) {
                return ['success' => false, 'error' => 'not_found'];
            }

            $sediu = $decoded['found'][0]['adresa_sediu_social'] ?? [];

            return [
                'success' => true,
                'error' => null,
                'client' => [
                    'cui' => (string) ($general['cui'] ?? $cui_digits),
                    'name' => trim((string) ($general['denumire'] ?? '')),
                    'address' => trim((string) ($general['adresa'] ?? '')),
                    'city' => trim((string) ($sediu['sdenumire_Localitate'] ?? '')),
                    'county' => trim((string) ($sediu['sdenumire_Judet'] ?? '')),
                    'reg_com' => trim((string) ($general['nrRegCom'] ?? '')),
                    'vat_payer' => (bool) ($decoded['found'][0]['inregistrare_scop_Tva']['scpTVA'] ?? false),
                ],
            ];
        } catch (Throwable $e) {
            log_message('error', '[anaf] CUI lookup failed for ' . $cui_digits . ': ' . $e->getMessage());

            return ['success' => false, 'error' => 'anaf_unavailable'];
        }
    }
}
