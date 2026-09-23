<?php

namespace Tests\Unit\AI;

use App\Enums\AIIntent;
use App\Services\AI\IntentClassifier;
use App\Services\AI\Providers\MockAIProvider;
use Tests\TestCase;

class IntentClassifierTest extends TestCase
{
    protected IntentClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new IntentClassifier(new MockAIProvider);
    }

    public function test_can_classify_property_inquiries(): void
    {
        $queries = [
            'Do you have any 500sqm plots available in Epe?',
            'What land is available in Lekki?',
            'Tell me about the duplexes in Grandview Meadows.',
        ];

        foreach ($queries as $q) {
            $result = $this->classifier->classify($q);
            $this->assertEquals(AIIntent::PropertyInquiry, $result->intent, "Failed for query: {$q}");
            $this->assertGreaterThanOrEqual(0.80, $result->confidence);
        }
    }

    public function test_can_classify_pricing_and_payment_inquiries(): void
    {
        $queries = [
            'How much does a plot cost?',
            'What is the initial deposit for Oasis Heights?',
            'Can I spread payment across 12 months in installments?',
            'Is there any active promo or discount on the land?',
        ];

        foreach ($queries as $q) {
            $result = $this->classifier->classify($q);
            $this->assertEquals(AIIntent::PricingInquiry, $result->intent, "Failed for query: {$q}");
        }
    }

    public function test_can_classify_inspection_booking_inquiries(): void
    {
        $queries = [
            'I would like to schedule a site inspection this Saturday.',
            'When can I visit the site to view the land?',
            'Can someone take me on a tour to Epe tomorrow?',
        ];

        foreach ($queries as $q) {
            $result = $this->classifier->classify($q);
            $this->assertEquals(AIIntent::InspectionBooking, $result->intent, "Failed for query: {$q}");
        }
    }

    public function test_can_classify_title_and_documentation_inquiries(): void
    {
        $queries = [
            'What is the legal title on the estate? Is it C of O?',
            'Does this property have a Governor\'s Consent or Registered Survey?',
            'Do you have Gazette title on the Epe land?',
        ];

        foreach ($queries as $q) {
            $result = $this->classifier->classify($q);
            $this->assertEquals(AIIntent::TitleVerification, $result->intent, "Failed for query: {$q}");
        }
    }

    public function test_can_classify_human_handover_requests(): void
    {
        $queries = [
            'I want to speak with a human agent please.',
            'Can a representative call me directly?',
            'Connect me with a sales manager.',
        ];

        foreach ($queries as $q) {
            $result = $this->classifier->classify($q);
            $this->assertEquals(AIIntent::HumanHandover, $result->intent, "Failed for query: {$q}");
            $this->assertTrue($result->requiresHumanTakeover);
        }
    }

    public function test_can_classify_greetings(): void
    {
        $queries = [
            'Hello Bamcom',
            'Good morning, are you open today?',
            'Hi there',
        ];

        foreach ($queries as $q) {
            $result = $this->classifier->classify($q);
            $this->assertEquals(AIIntent::Greeting, $result->intent, "Failed for query: {$q}");
        }
    }

    public function test_extracts_real_estate_entities(): void
    {
        $query = 'I have a budget of 25m and I want 500 sqm in Epe on Saturday.';
        $entities = $this->classifier->extractEntities($query);

        $this->assertEquals('Epe', $entities['location'] ?? null);
        $this->assertEquals('500 SQM', $entities['plot_size'] ?? null);
        $this->assertEquals(25000000.0, $entities['budget_numeric'] ?? null);
        $this->assertEquals('Saturday', $entities['preferred_day'] ?? null);
    }
}
