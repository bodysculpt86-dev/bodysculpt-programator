<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

/**
 * Tests for the payment link slug generator helper.
 */
class PaymentLinkHelperTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once APPPATH . 'helpers/payment_link_helper.php';
    }

    public function testGeneratesEightCharacterSlugByDefault(): void
    {
        $slug = generate_payment_slug();

        $this->assertSame(8, strlen($slug));
    }

    public function testSlugIsCaseSensitiveAlphanumeric(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $slug = generate_payment_slug();

            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{8}$/', $slug);
        }
    }

    public function testRespectsCustomLength(): void
    {
        $this->assertSame(6, strlen(generate_payment_slug(6)));
        $this->assertSame(16, strlen(generate_payment_slug(16)));
    }

    public function testSlugsAreUniqueInPractice(): void
    {
        $generated = [];

        for ($i = 0; $i < 1000; $i++) {
            $generated[] = generate_payment_slug();
        }

        $this->assertSame(1000, count(array_unique($generated)), 'Duplicate slugs were generated.');
    }

    public function testSlugMatchesEndpointValidationPattern(): void
    {
        // The Pay controller accepts /^[A-Za-z0-9]{1,16}$/ only.
        $slug = generate_payment_slug();

        $this->assertSame(1, preg_match('/^[A-Za-z0-9]{1,16}$/', $slug));
    }
}
