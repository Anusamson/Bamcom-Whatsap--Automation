<?php

namespace Tests\Unit\AI;

use App\Enums\KnowledgeCategory;
use App\Enums\KnowledgeStatus;
use App\Models\KnowledgeRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_knowledge_record_can_be_created_with_uuid(): void
    {
        $user = User::factory()->create();

        $record = KnowledgeRecord::create([
            'title' => 'Bamcom CAC Registration & Incorporation Details',
            'category' => KnowledgeCategory::CompanyInformation,
            'content' => 'Bamcom Properties is registered under RC 1849204.',
            'status' => KnowledgeStatus::Active,
            'priority' => 100,
            'effective_date' => now()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'keywords' => ['cac', 'rc', 'legal'],
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($record->uuid);
        $this->assertEquals('Bamcom CAC Registration & Incorporation Details', $record->title);
        $this->assertEquals(KnowledgeCategory::CompanyInformation, $record->category);
        $this->assertEquals(KnowledgeStatus::Active, $record->status);
        $this->assertEquals(100, $record->priority);
        $this->assertCount(3, $record->keywords);
        $this->assertTrue($record->isActive());
    }

    public function test_scope_active_includes_only_currently_valid_active_records(): void
    {
        // 1. Active with no dates -> Active
        $alwaysActive = KnowledgeRecord::factory()->create([
            'title' => 'Always Active Record',
            'status' => KnowledgeStatus::Active,
            'effective_date' => null,
            'expiration_date' => null,
        ]);

        // 2. Active with valid date range -> Active
        $validWindow = KnowledgeRecord::factory()->create([
            'title' => 'Valid Window Record',
            'status' => KnowledgeStatus::Active,
            'effective_date' => now()->subDays(5),
            'expiration_date' => now()->addDays(5),
        ]);

        // 3. Draft status -> Excluded
        $draft = KnowledgeRecord::factory()->create([
            'title' => 'Draft Record',
            'status' => KnowledgeStatus::Draft,
            'effective_date' => now()->subDays(5),
            'expiration_date' => now()->addDays(5),
        ]);

        // 4. Archived status -> Excluded
        $archived = KnowledgeRecord::factory()->create([
            'title' => 'Archived Record',
            'status' => KnowledgeStatus::Archived,
            'effective_date' => now()->subDays(5),
            'expiration_date' => now()->addDays(5),
        ]);

        // 5. Active but expired in past -> Excluded
        $expired = KnowledgeRecord::factory()->create([
            'title' => 'Expired Record',
            'status' => KnowledgeStatus::Active,
            'effective_date' => now()->subMonth(),
            'expiration_date' => now()->subDay(),
        ]);

        // 6. Active but future effective date -> Excluded
        $future = KnowledgeRecord::factory()->create([
            'title' => 'Future Record',
            'status' => KnowledgeStatus::Active,
            'effective_date' => now()->addWeek(),
            'expiration_date' => now()->addMonths(2),
        ]);

        $activeRecords = KnowledgeRecord::query()->active()->pluck('title')->toArray();

        $this->assertContains('Always Active Record', $activeRecords);
        $this->assertContains('Valid Window Record', $activeRecords);
        $this->assertNotContains('Draft Record', $activeRecords);
        $this->assertNotContains('Archived Record', $activeRecords);
        $this->assertNotContains('Expired Record', $activeRecords);
        $this->assertNotContains('Future Record', $activeRecords);
    }

    public function test_is_active_and_helper_flags_evaluate_correctly(): void
    {
        $activeRecord = KnowledgeRecord::factory()->active()->create();
        $this->assertTrue($activeRecord->isActive());
        $this->assertFalse($activeRecord->isExpired());
        $this->assertFalse($activeRecord->isScheduled());

        $draftRecord = KnowledgeRecord::factory()->draft()->create();
        $this->assertFalse($draftRecord->isActive());

        $expiredRecord = KnowledgeRecord::factory()->expired()->create();
        $this->assertFalse($expiredRecord->isActive());
        $this->assertTrue($expiredRecord->isExpired());

        $scheduledRecord = KnowledgeRecord::factory()->futureEffective()->create();
        $this->assertFalse($scheduledRecord->isActive());
        $this->assertTrue($scheduledRecord->isScheduled());
    }

    public function test_scope_category_filters_by_enum_and_string(): void
    {
        KnowledgeRecord::factory()->create([
            'category' => KnowledgeCategory::Faq,
            'title' => 'FAQ Item',
        ]);

        KnowledgeRecord::factory()->create([
            'category' => KnowledgeCategory::ObjectionHandling,
            'title' => 'Objection Item',
        ]);

        $faqResults = KnowledgeRecord::query()->category(KnowledgeCategory::Faq)->get();
        $this->assertCount(1, $faqResults);
        $this->assertEquals('FAQ Item', $faqResults->first()->title);

        $objectionResults = KnowledgeRecord::query()->category('objection_handling')->get();
        $this->assertCount(1, $objectionResults);
        $this->assertEquals('Objection Item', $objectionResults->first()->title);
    }

    public function test_scope_prioritized_orders_by_priority_descending(): void
    {
        $low = KnowledgeRecord::factory()->create(['priority' => 10, 'title' => 'Low Priority']);
        $high = KnowledgeRecord::factory()->create(['priority' => 100, 'title' => 'High Priority']);
        $med = KnowledgeRecord::factory()->create(['priority' => 50, 'title' => 'Medium Priority']);

        $ordered = KnowledgeRecord::query()->prioritized()->pluck('title')->toArray();

        $this->assertEquals(['High Priority', 'Medium Priority', 'Low Priority'], $ordered);
    }

    public function test_scope_search_matches_title_content_and_keywords(): void
    {
        KnowledgeRecord::factory()->create([
            'title' => 'Dangote Refinery Economic Impact',
            'content' => 'Large scale industrial growth in Ibeju Lekki.',
            'keywords' => ['refinery', 'petrochemical'],
        ]);

        KnowledgeRecord::factory()->create([
            'title' => 'Site Inspection Transport Rules',
            'content' => 'Company bus departures from Lekki Phase 1.',
            'keywords' => ['transport', 'pickup'],
        ]);

        $matchTitle = KnowledgeRecord::query()->search('Dangote')->get();
        $this->assertCount(1, $matchTitle);
        $this->assertEquals('Dangote Refinery Economic Impact', $matchTitle->first()->title);

        $matchContent = KnowledgeRecord::query()->search('departures')->get();
        $this->assertCount(1, $matchContent);
        $this->assertEquals('Site Inspection Transport Rules', $matchContent->first()->title);

        $matchKeyword = KnowledgeRecord::query()->search('petrochemical')->get();
        $this->assertCount(1, $matchKeyword);
        $this->assertEquals('Dangote Refinery Economic Impact', $matchKeyword->first()->title);
    }
}
