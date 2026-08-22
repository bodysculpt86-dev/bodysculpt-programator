<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

class PhoneHelperTest extends TestCase {
    /**
     * @dataProvider internationalPhoneProvider
     */
    public function testNormalizeInternationalPhone(?string $input, ?string $expected)
    {
        $this->assertSame($expected, normalize_international_phone($input));
    }

    public static function internationalPhoneProvider(): array
    {
        return [
            'empty' => [null, null],
            'blank' => ['   ', null],
            'garbage' => ['abc', null],
            'too short' => ['123', null],

            // Legacy Romanian local formats still default to Romania.
            'ro local 07' => ['0722334455', '40722334455'],
            'ro local +40' => ['+40722334455', '40722334455'],
            'ro local 0040' => ['0040722334455', '40722334455'],
            'ro local bare 407' => ['40722334455', '40722334455'],

            // Foreign numbers with an explicit international prefix.
            'italy +39' => ['+393123456789', '393123456789'],
            'italy 0039' => ['00393123456789', '393123456789'],
            'germany +49' => ['+4915112345678', '4915112345678'],
            'us +1' => ['+1 202 555 0134', '12025550134'],
            'moldova +373' => ['+37369123456', '37369123456'],

            // A Romanian landline (10 digits starting 0 but not 07) stays invalid.
            'ro landline' => ['0212345678', null],
        ];
    }
}
