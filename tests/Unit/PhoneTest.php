<?php

namespace Tests\Unit;

use App\Support\Phone;
use Tests\TestCase;

class PhoneTest extends TestCase
{
    public function test_normalizes_saudi_mobile_in_common_formats(): void
    {
        foreach (['0551234567', '551234567', '+966551234567', '00966551234567', '966551234567', '055 123 4567', '٠٥٥١٢٣٤٥٦٧'] as $input) {
            $this->assertSame('966551234567', Phone::normalize($input), $input);
        }
    }

    public function test_rejects_invalid_numbers(): void
    {
        foreach (['', '12345', '0112345678', 'abc', '05512345'] as $input) {
            $this->assertNull(Phone::normalize($input), $input);
        }
    }

    public function test_formats_and_masks_for_display(): void
    {
        $this->assertSame('055 123 4567', Phone::format('966551234567'));
        $this->assertSame('05• ••• 4567', Phone::mask('966551234567'));
    }
}
