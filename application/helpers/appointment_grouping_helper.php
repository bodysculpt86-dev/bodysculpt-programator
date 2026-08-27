<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Same-day appointment grouping helper.
 * ---------------------------------------------------------------------------- */

if (!function_exists('same_day_group_gap_minutes')) {
    /**
     * Read the maximum pause (in minutes) for two consecutive appointments to be
     * considered part of the same group.
     *
     * Env var SAME_DAY_GROUP_GAP_MINUTES overrides the Config constant, which
     * defaults to 90.
     *
     * @return int
     */
    function same_day_group_gap_minutes(): int
    {
        $value = getenv('SAME_DAY_GROUP_GAP_MINUTES');

        if ($value !== false && $value !== '') {
            return max(0, (int) $value);
        }

        if (defined('Config::SAME_DAY_GROUP_GAP_MINUTES')) {
            return max(0, (int) constant('Config::SAME_DAY_GROUP_GAP_MINUTES'));
        }

        return 90;
    }
}

if (!function_exists('same_day_group_excluded_statuses')) {
    /**
     * Appointment statuses that must never be grouped (and never reminded as part of a group).
     *
     * Covers the Romanian literals used by the reminder flow, the English variants,
     * and the closing/payment statuses configured in appointment_closing_statuses.
     *
     * @return array
     */
    function same_day_group_excluded_statuses(): array
    {
        $statuses = [
            'Anulat',
            'Anulat de client',
            'Schita',
            'Nu s-a prezentat',
            'Cancelled',
            'Draft',
        ];

        if (defined('APPOINTMENT_STATUS_CANCELLED_BY_CLIENT')) {
            $statuses[] = APPOINTMENT_STATUS_CANCELLED_BY_CLIENT;
        }

        if (function_exists('setting')) {
            $closing = json_decode((string) setting('appointment_closing_statuses', '[]'), true);

            if (is_array($closing)) {
                $statuses = array_merge($statuses, $closing);
            }
        }

        return array_values(array_unique($statuses));
    }
}

if (!function_exists('group_appointments_same_day_chain')) {
    /**
     * Group appointments into same-day chains per customer.
     *
     * Appointments whose status is listed in $excluded_statuses are dropped first.
     * The remaining appointments are bucketed by (id_users_customer, calendar date) and,
     * within each bucket, sorted by start_datetime. A new chain starts whenever the pause
     * between two consecutive appointments exceeds $gap_minutes.
     *
     * The result is a list of groups (chains); each group is ordered by start_datetime
     * ascending and the groups themselves are ordered by their earliest start.
     *
     * @param array $appointments List of appointment arrays (need id, id_users_customer, start_datetime, end_datetime, status).
     * @param int   $gap_minutes Maximum pause in minutes for two appointments to stay chained.
     * @param array $excluded_statuses Status values to exclude from grouping.
     *
     * @return array
     */
    function group_appointments_same_day_chain(array $appointments, int $gap_minutes, array $excluded_statuses = []): array
    {
        $excluded = array_flip($excluded_statuses);

        $buckets = [];

        foreach ($appointments as $appointment) {
            $status = $appointment['status'] ?? '';

            if (isset($excluded[$status])) {
                continue;
            }

            $customer = $appointment['id_users_customer'] ?? null;
            $date = substr((string) ($appointment['start_datetime'] ?? ''), 0, 10);
            $key = $customer . '|' . $date;

            $buckets[$key][] = $appointment;
        }

        $groups = [];

        foreach ($buckets as $bucket) {
            usort($bucket, static function (array $a, array $b): int {
                return strcmp((string) ($a['start_datetime'] ?? ''), (string) ($b['start_datetime'] ?? ''));
            });

            $chain = [];
            $previous_end = null;

            foreach ($bucket as $appointment) {
                if ($previous_end !== null) {
                    $gap_seconds = strtotime((string) $appointment['start_datetime']) - strtotime($previous_end);

                    if ($gap_seconds > $gap_minutes * 60) {
                        $groups[] = $chain;
                        $chain = [];
                    }
                }

                $chain[] = $appointment;

                $end = !empty($appointment['end_datetime']) ? $appointment['end_datetime'] : ($appointment['start_datetime'] ?? '');

                $previous_end = $end;
            }

            if (!empty($chain)) {
                $groups[] = $chain;
            }
        }

        usort($groups, static function (array $a, array $b): int {
            return strcmp((string) ($a[0]['start_datetime'] ?? ''), (string) ($b[0]['start_datetime'] ?? ''));
        });

        return $groups;
    }
}

if (!function_exists('same_day_group_containing')) {
    /**
     * Find the group that contains the given appointment id.
     *
     * @param array $groups Groups returned by group_appointments_same_day_chain().
     * @param int|string $appointment_id Appointment id to locate.
     *
     * @return array|null The matching group, or null when not found.
     */
    function same_day_group_containing(array $groups, $appointment_id): ?array
    {
        foreach ($groups as $group) {
            foreach ($group as $appointment) {
                if ((string) ($appointment['id'] ?? '') === (string) $appointment_id) {
                    return $group;
                }
            }
        }

        return null;
    }
}
