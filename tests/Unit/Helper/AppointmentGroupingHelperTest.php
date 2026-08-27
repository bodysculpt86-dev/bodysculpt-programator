<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

/**
 * Tests for the same-day appointment grouping helper.
 *
 * These exercise the pure grouping algorithm (no database), so they are independent
 * of the SAME_DAY_GROUP_GAP_MINUTES config and of the concrete status values in the DB.
 */
class AppointmentGroupingHelperTest extends TestCase
{
    private function appointment(int $id, int $customer, string $start, string $end, string $status = 'Booked'): array
    {
        return [
            'id' => $id,
            'id_users_customer' => $customer,
            'start_datetime' => $start,
            'end_datetime' => $end,
            'status' => $status,
        ];
    }

    /**
     * @param array $groups
     *
     * @return array List of lists of appointment ids.
     */
    private function groupIds(array $groups): array
    {
        return array_map(fn(array $group) => array_column($group, 'id'), $groups);
    }

    public function testTwoConsecutiveAppointmentsFormOneGroup(): void
    {
        $appointments = [
            $this->appointment(1, 10, '2026-08-27 10:00:00', '2026-08-27 10:30:00'),
            $this->appointment(2, 10, '2026-08-27 11:00:00', '2026-08-27 11:30:00'),
        ];

        $groups = group_appointments_same_day_chain($appointments, 90, ['Anulat']);

        $this->assertSame([[1, 2]], $this->groupIds($groups));
    }

    public function testThreeAppointmentsFormOneChain(): void
    {
        $appointments = [
            $this->appointment(1, 10, '2026-08-27 09:00:00', '2026-08-27 09:30:00'),
            $this->appointment(2, 10, '2026-08-27 10:30:00', '2026-08-27 11:00:00'),
            $this->appointment(3, 10, '2026-08-27 12:00:00', '2026-08-27 12:30:00'),
        ];

        $groups = group_appointments_same_day_chain($appointments, 90, ['Anulat']);

        $this->assertSame([[1, 2, 3]], $this->groupIds($groups));
    }

    public function testGapLargerThanThresholdSplitsIntoTwoGroups(): void
    {
        $appointments = [
            $this->appointment(1, 10, '2026-08-27 10:00:00', '2026-08-27 10:30:00'),
            $this->appointment(2, 10, '2026-08-27 13:00:00', '2026-08-27 13:30:00'),
        ];

        $groups = group_appointments_same_day_chain($appointments, 90, ['Anulat']);

        $this->assertSame([[1], [2]], $this->groupIds($groups));
    }

    public function testCancelledAppointmentIsExcludedFromGroup(): void
    {
        $excluded = ['Anulat', 'Anulat de client', 'Schita', 'Nu s-a prezentat', 'Cancelled', 'Draft'];

        $appointments = [
            $this->appointment(1, 10, '2026-08-27 10:00:00', '2026-08-27 10:30:00'),
            $this->appointment(2, 10, '2026-08-27 11:00:00', '2026-08-27 11:30:00', 'Anulat'),
        ];

        $groups = group_appointments_same_day_chain($appointments, 90, $excluded);

        $this->assertSame([[1]], $this->groupIds($groups));
    }

    public function testGroupIsOrderedByStartTime(): void
    {
        $appointments = [
            $this->appointment(2, 10, '2026-08-27 11:00:00', '2026-08-27 11:30:00'),
            $this->appointment(1, 10, '2026-08-27 10:00:00', '2026-08-27 10:30:00'),
        ];

        $groups = group_appointments_same_day_chain($appointments, 90, ['Anulat']);

        $this->assertSame([[1, 2]], $this->groupIds($groups));
    }

    public function testMorningAndEveningVisitsAreNotGrouped(): void
    {
        $appointments = [
            $this->appointment(1, 10, '2026-08-27 09:00:00', '2026-08-27 09:30:00'),
            $this->appointment(2, 10, '2026-08-27 09:45:00', '2026-08-27 10:15:00'),
            $this->appointment(3, 10, '2026-08-27 18:00:00', '2026-08-27 18:30:00'),
        ];

        $groups = group_appointments_same_day_chain($appointments, 90, ['Anulat']);

        $this->assertSame([[1, 2], [3]], $this->groupIds($groups));
    }

    public function testDifferentCustomersAreNeverGrouped(): void
    {
        $appointments = [
            $this->appointment(1, 10, '2026-08-27 10:00:00', '2026-08-27 10:30:00'),
            $this->appointment(2, 20, '2026-08-27 11:00:00', '2026-08-27 11:30:00'),
        ];

        $groups = group_appointments_same_day_chain($appointments, 90, ['Anulat']);

        $this->assertSame([[1], [2]], $this->groupIds($groups));
    }
}
