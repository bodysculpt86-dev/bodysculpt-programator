<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

/**
 * Tests for the appointment creator label helper.
 *
 * The rule is: appointments created by a logged-in user name that user, public-form
 * appointments are labelled as online, and rows that predate creator tracking must
 * never be guessed — they render as an em dash.
 */
class AppointmentCreatorHelperTest extends TestCase
{
    private const LABELS = [
        'meta_leads' => 'din Meta Leads',
        'online' => 'Online',
        'api' => 'API',
        'by' => 'de',
    ];

    public function testLoggedInUserIsNamedWithoutASourceSuffix(): void
    {
        $this->assertSame(
            'Ana',
            appointment_creator_label('Ana', 'admin', self::LABELS),
        );
    }

    public function testMetaLeadsAppointmentNamesTheUserAndTheSource(): void
    {
        $this->assertSame(
            'Ana (din Meta Leads)',
            appointment_creator_label('Ana', 'meta_leads', self::LABELS),
        );
    }

    public function testPublicFormAppointmentIsLabelledOnline(): void
    {
        $this->assertSame(
            'Online',
            appointment_creator_label(null, 'online', self::LABELS),
        );
    }

    public function testApiAppointmentIsLabelledApi(): void
    {
        $this->assertSame(
            'API',
            appointment_creator_label(null, 'api', self::LABELS),
        );
    }

    public function testRowWithoutCreatorTrackingRendersAsEmDash(): void
    {
        $this->assertSame(
            '—',
            appointment_creator_label(null, null, self::LABELS),
        );
    }

    public function testEmptyCreatorNameIsTreatedAsUnknown(): void
    {
        $this->assertSame(
            'Online',
            appointment_creator_label('', 'online', self::LABELS),
        );
    }

    public function testUnknownSourceWithNoUserStillRendersAsEmDash(): void
    {
        $this->assertSame(
            '—',
            appointment_creator_label(null, '', self::LABELS),
        );
    }

    public function testNameWinsOverAnUnrecognisedSource(): void
    {
        $this->assertSame(
            'Ana',
            appointment_creator_label('Ana', 'something-else', self::LABELS),
        );
    }

    public function testSuffixIntroducesANamedUserWithAPreposition(): void
    {
        $this->assertSame(
            ' de Ana',
            appointment_creator_suffix('Ana', 'admin', self::LABELS),
        );
    }

    public function testSuffixKeepsTheMetaLeadsMention(): void
    {
        $this->assertSame(
            ' de Ana (din Meta Leads)',
            appointment_creator_suffix('Ana', 'meta_leads', self::LABELS),
        );
    }

    public function testSuffixSeparatesASourceInsteadOfPrepositioningIt(): void
    {
        $this->assertSame(
            ' · Online',
            appointment_creator_suffix(null, 'online', self::LABELS),
        );
    }

    public function testSuffixStillReportsTheEmDashForUntrackedRows(): void
    {
        $this->assertSame(
            ' · —',
            appointment_creator_suffix(null, null, self::LABELS),
        );
    }
}
