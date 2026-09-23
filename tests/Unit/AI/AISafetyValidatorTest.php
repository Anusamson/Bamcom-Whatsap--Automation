<?php

namespace Tests\Unit\AI;

use App\Services\AI\Safety\AISafetyValidator;
use Tests\TestCase;

class AISafetyValidatorTest extends TestCase
{
    protected AISafetyValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AISafetyValidator;
    }

    public function test_valid_real_estate_response_passes(): void
    {
        $content = "Welcome to *Bamcom Properties*! 🏢\n\n"
            ."We have prime 500 SQM plots available at *Oasis Heights, Epe* for *₦12,500,000* with verified Certificate of Occupancy (C of O).\n"
            ."Initial deposit is *₦3,000,000* with 6-month flexible spreads.\n\n"
            .'Would you like to schedule an inspection this Saturday at 10:00 AM? 🚗';

        $result = $this->validator->validate($content);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['violations']);
        $this->assertEquals($content, $result['sanitized_content']);
    }

    public function test_rejects_empty_content(): void
    {
        $result = $this->validator->validate('   ');

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Generated content is empty.', $result['violations']);
    }

    public function test_rejects_excessive_length_exceeding_whatsapp_limits(): void
    {
        $longContent = str_repeat('Bamcom verified properties in Lagos. ', 150); // > 4096 chars
        $result = $this->validator->validate($longContent);

        $this->assertFalse($result['is_valid']);
        $this->assertTrue(collect($result['violations'])->some(fn ($v) => str_contains($v, 'exceeds WhatsApp maximum length')));
    }

    public function test_rejects_prohibited_guaranteed_profit_and_speculative_promises(): void
    {
        $content = 'Buy this plot now and enjoy a 100% return guaranteed profit within 60 days!';
        $result = $this->validator->validate($content);

        $this->assertFalse($result['is_valid']);
        $this->assertTrue(collect($result['violations'])->some(fn ($v) => str_contains($v, 'guaranteed profit')));
    }

    public function test_rejects_abusive_language(): void
    {
        $content = 'Stop asking questions you idiot. Buy now or leave.';
        $result = $this->validator->validate($content);

        $this->assertFalse($result['is_valid']);
        $this->assertTrue(collect($result['violations'])->some(fn ($v) => str_contains($v, 'inappropriate language')));
    }

    public function test_rejects_payment_to_personal_account(): void
    {
        $content = 'To secure the plot, make a transfer of the deposit to my personal bank account.';
        $result = $this->validator->validate($content);

        $this->assertFalse($result['is_valid']);
        $this->assertTrue(collect($result['violations'])->some(fn ($v) => str_contains($v, 'payment must strictly be directed to corporate accounts')));
    }
}
