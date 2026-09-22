<?php

namespace Tests\Unit;

use App\Services\Contact\PhoneNormalizerService;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerServiceTest extends TestCase
{
    protected PhoneNormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new PhoneNormalizerService;
    }

    public function test_normalizes_local_nigerian_number_starting_with_zero(): void
    {
        $input = '08012345678';
        $expected = '+2348012345678';

        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public function test_strips_spaces_brackets_and_hyphens(): void
    {
        $input = '+234 (801) 234-5678';
        $expected = '+2348012345678';

        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public function test_converts_double_zero_prefix_to_plus(): void
    {
        $input = '002348012345678';
        $expected = '+2348012345678';

        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public function test_prepends_plus_to_number_starting_with_country_code(): void
    {
        $input = '2348012345678';
        $expected = '+2348012345678';

        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public function test_preserves_valid_international_e164_numbers(): void
    {
        $input = '+1 202 555 0123';
        $expected = '+12025550123';

        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public function test_validates_phone_numbers_correctly(): void
    {
        $this->assertTrue($this->normalizer->isValid('08012345678'));
        $this->assertTrue($this->normalizer->isValid('+2348012345678'));
        $this->assertTrue($this->normalizer->isValid('+12025550123'));
        $this->assertFalse($this->normalizer->isValid('123'));
        $this->assertFalse($this->normalizer->isValid(''));
    }

    public function test_generates_whatsapp_url(): void
    {
        $phone = '+2348012345678';
        $url = $this->normalizer->toWhatsAppUrl($phone, 'Hello Bamcom');

        $this->assertEquals('https://wa.me/2348012345678?text=Hello%20Bamcom', $url);
    }

    public function test_formats_nigerian_number_for_display(): void
    {
        $phone = '+2348012345678';
        $formatted = $this->normalizer->format($phone);

        $this->assertEquals('+234 801 234 5678', $formatted);
    }
}
