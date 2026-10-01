<?php

namespace Tests\Feature\Email;

use App\Enums\EmailMessageType;
use App\Enums\EmailTemplateCategory;
use App\Enums\EmailTemplateStatus;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\Inspection;
use App\Models\Property;
use App\Models\User;
use App\Services\Email\EmailTemplateService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailTemplateSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $regularUser;

    protected EmailTemplateService $templateService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'status' => UserStatus::Active,
        ]);
        $this->superAdmin->givePermissionTo(PermissionEnum::values());

        $this->regularUser = User::factory()->create([
            'role' => UserRole::SalesExecutive,
            'status' => UserStatus::Active,
        ]);

        $this->templateService = app(EmailTemplateService::class);
    }

    /**
     * Test template list endpoint returns all 10 required categories and templates.
     */
    public function test_user_with_permission_can_view_templates_and_all_ten_categories(): void
    {
        EmailTemplate::factory()->create([
            'name' => 'VIP Property Showcase',
            'category' => EmailTemplateCategory::Property->value,
            'status' => EmailTemplateStatus::Active->value,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson(route('email-templates.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'templates' => ['data'],
            'categories',
            'statuses',
        ]);

        $categories = collect($response->json('categories'))->pluck('value')->all();

        $expectedCategories = [
            'marketing',
            'property',
            'welcome',
            'follow_up',
            'inspection',
            'payment',
            'newsletter',
            'promotion',
            'transactional',
            're_engagement',
        ];

        foreach ($expectedCategories as $expected) {
            $this->assertContains($expected, $categories, "Category {$expected} must exist in the category catalog.");
        }
    }

    /**
     * Test unauthorized users cannot view or manipulate templates.
     */
    public function test_user_without_permission_is_forbidden_from_templates(): void
    {
        $template = EmailTemplate::factory()->create();

        $this->actingAs($this->regularUser)
            ->get(route('email-templates.index'))
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->get(route('email-templates.show', $template))
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->postJson(route('email-templates.store'), [
                'name' => 'Forbidden Template',
                'subject' => 'Unauthorized',
                'category' => 'marketing',
                'body_html' => '<p>Test</p>',
            ])
            ->assertForbidden();
    }

    /**
     * Test templates can be created for all 10 supported categories.
     */
    public function test_can_create_templates_for_all_supported_categories(): void
    {
        $categories = EmailTemplateCategory::cases();

        foreach ($categories as $category) {
            $bodyHtml = $category->requiresUnsubscribe()
                ? '<p>Exclusive update for you.</p><a href="{{ unsubscribe_url }}">Unsubscribe</a>'
                : '<p>Standard notice for your transaction.</p>';

            $response = $this->actingAs($this->superAdmin)
                ->postJson(route('email-templates.store'), [
                    'name' => "Template for {$category->label()}",
                    'subject' => "Subject for {$category->label()} - {{ contact.first_name }}",
                    'preheader' => "Preheader snippet for {$category->label()}",
                    'category' => $category->value,
                    'status' => EmailTemplateStatus::Active->value,
                    'body_html' => $bodyHtml,
                    'body_plain' => strip_tags($bodyHtml),
                ]);

            $response->assertCreated();
            $this->assertDatabaseHas('email_templates', [
                'name' => "Template for {$category->label()}",
                'category' => $category->value,
                'status' => EmailTemplateStatus::Active->value,
            ]);
        }
    }

    /**
     * Test marketing templates enforce CAN-SPAM unsubscribe link requirement.
     */
    public function test_marketing_templates_fail_validation_without_unsubscribe_link(): void
    {
        $marketingCategories = [
            EmailTemplateCategory::Marketing->value,
            EmailTemplateCategory::Newsletter->value,
            EmailTemplateCategory::Promotion->value,
            EmailTemplateCategory::ReEngagement->value,
        ];

        foreach ($marketingCategories as $category) {
            $response = $this->actingAs($this->superAdmin)
                ->postJson(route('email-templates.store'), [
                    'name' => "Non-compliant {$category}",
                    'subject' => 'Big Discount Today!',
                    'category' => $category,
                    'status' => 'active',
                    'body_html' => '<p>Buy now and save 50%! Click here to order.</p>', // Missing unsubscribe!
                ]);

            $response->assertUnprocessable();
            $response->assertJsonValidationErrors(['body_html']);
        }
    }

    /**
     * Test non-marketing templates do not require an unsubscribe link.
     */
    public function test_non_marketing_templates_pass_validation_without_unsubscribe_link(): void
    {
        $nonMarketingCategories = [
            EmailTemplateCategory::Transactional->value,
            EmailTemplateCategory::Inspection->value,
            EmailTemplateCategory::Payment->value,
            EmailTemplateCategory::Welcome->value,
            EmailTemplateCategory::Property->value,
            EmailTemplateCategory::FollowUp->value,
        ];

        foreach ($nonMarketingCategories as $category) {
            $response = $this->actingAs($this->superAdmin)
                ->postJson(route('email-templates.store'), [
                    'name' => "Compliant {$category}",
                    'subject' => "Confirmation for {$category}",
                    'category' => $category,
                    'status' => 'active',
                    'body_html' => '<p>Your request has been successfully recorded in our CRM.</p>',
                ]);

            $response->assertCreated();
        }
    }

    /**
     * Test template update and status transitions.
     */
    public function test_can_update_email_template_and_status(): void
    {
        $template = EmailTemplate::factory()->create([
            'name' => 'Original Name',
            'status' => EmailTemplateStatus::Draft->value,
            'category' => EmailTemplateCategory::Inspection->value,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->putJson(route('email-templates.update', $template), [
                'name' => 'Updated Inspection Confirmation',
                'subject' => 'Inspection Confirmed: {{ property.name }}',
                'preheader' => 'Your site inspection has been confirmed',
                'category' => EmailTemplateCategory::Inspection->value,
                'status' => EmailTemplateStatus::Active->value,
                'body_html' => '<h3>Inspection Confirmed</h3><p>Date: {{ inspection.date }}</p>',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('email_templates', [
            'id' => $template->id,
            'name' => 'Updated Inspection Confirmation',
            'subject' => 'Inspection Confirmed: {{ property.name }}',
            'status' => EmailTemplateStatus::Active->value,
        ]);
    }

    /**
     * Test template deletion.
     */
    public function test_can_delete_email_template(): void
    {
        $template = EmailTemplate::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson(route('email-templates.destroy', $template));

        $response->assertOk();
        $this->assertSoftDeleted('email_templates', [
            'id' => $template->id,
        ]);
    }

    /**
     * Test secure rendering strips arbitrary PHP and JavaScript execution attempts.
     */
    public function test_secure_rendering_prevents_arbitrary_php_and_script_execution(): void
    {
        $maliciousHtml = '
            <h1>Welcome {{ contact.first_name }}</h1>
            <?php echo "HACKED_PHP_EXECUTION"; ?>
            <?= system("whoami"); ?>
            <% asp_tag %>
            <script type="text/javascript">alert("XSS_ATTACK");</script>
            <p>Your property is {{ property.name }}.</p>
        ';

        $rendered = $this->templateService->render(
            htmlTemplate: $maliciousHtml,
            variables: [
                'contact' => ['first_name' => 'Amara'],
                'property' => ['name' => 'Palm Grove Estate'],
            ],
            wrapWithBrand: false
        );

        $this->assertStringNotContainsString('<?php', $rendered['html']);
        $this->assertStringNotContainsString('HACKED_PHP_EXECUTION', $rendered['html']);
        $this->assertStringNotContainsString('<?= system', $rendered['html']);
        $this->assertStringNotContainsString('<script', $rendered['html']);
        $this->assertStringNotContainsString('alert("XSS_ATTACK")', $rendered['html']);
        $this->assertStringContainsString('Welcome Amara', $rendered['html']);
        $this->assertStringContainsString('Your property is Palm Grove Estate.', $rendered['html']);
    }

    /**
     * Test dynamic variable interpolation for contact, agent, property, and inspection.
     */
    public function test_dynamic_variable_interpolation_for_all_entities(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'Chinedu',
            'last_name' => 'Okonkwo',
            'email' => 'chinedu@example.com',
        ]);

        $agent = User::factory()->create([
            'name' => 'Fatima Bello',
            'email' => 'fatima.bello@bamcomcrm.com',
        ]);

        $property = Property::factory()->create([
            'title' => 'Ocean Crest Penthouse',
        ]);

        $inspection = Inspection::factory()->create([
            'contact_id' => $contact->id,
            'property_id' => $property->id,
            'inspection_date' => now()->addDays(3)->toDateString(),
            'inspection_time' => '14:00',
        ]);

        $templateHtml = '
            Hello {{ contact.first_name }} {{ contact.last_name }} ({{ contact.email }}),
            Your dedicated sales advisor is {{ agent.name }} ({{ agent.email }}).
            Regarding property: {{ property.name }}.
            Inspection scheduled for: {{ inspection.date }} at {{ inspection.time }}.
        ';

        $variables = $this->templateService->buildVariables(
            contact: $contact,
            agent: $agent,
            property: $property,
            inspection: $inspection
        );

        $rendered = $this->templateService->render(
            htmlTemplate: $templateHtml,
            variables: $variables,
            wrapWithBrand: false
        );

        $this->assertStringContainsString('Hello Chinedu Okonkwo (chinedu@example.com)', $rendered['html']);
        $this->assertStringContainsString('Your dedicated sales advisor is Fatima Bello (fatima.bello@bamcomcrm.com)', $rendered['html']);
        $this->assertStringContainsString('Regarding property: Ocean Crest Penthouse', $rendered['html']);
        $this->assertStringContainsString('14:00', $rendered['html']);
    }

    /**
     * Test preheader snippet is rendered as a hidden preview element.
     */
    public function test_preheader_is_rendered_in_html_as_preview_snippet(): void
    {
        $rendered = $this->templateService->render(
            htmlTemplate: '<p>Main email body content</p>',
            variables: ['contact' => ['first_name' => 'Zainab']],
            preheader: 'Special invitation for {{ contact.first_name }}',
            wrapWithBrand: true
        );

        $this->assertStringContainsString('Special invitation for Zainab', $rendered['html']);
        $this->assertStringContainsString('display:none', $rendered['html']);
        $this->assertStringContainsString('mso-hide:all', $rendered['html']);
    }

    /**
     * Test Bamcom branded wrapper injects standard header and footer components.
     */
    public function test_bamcom_branded_wrapper_injects_header_and_footer(): void
    {
        $rendered = $this->templateService->render(
            htmlTemplate: '<p>Personalized investment advice</p>',
            variables: [
                'unsubscribe_url' => 'https://bamcomcrm.com/unsubscribe/token123',
            ],
            wrapWithBrand: true
        );

        // Header check
        $this->assertStringContainsString('BAMCOM <span style="color: #38bdf8;">CRM</span>', $rendered['html']);
        $this->assertStringContainsString('Real Estate &bull; Site Inspections &bull; Investments', $rendered['html']);

        // Body check
        $this->assertStringContainsString('Personalized investment advice', $rendered['html']);

        // Footer check
        $this->assertStringContainsString('Bamcom Real Estate &amp; Investments Ltd', $rendered['html']);
        $this->assertStringContainsString('Lekki Phase 1, Lagos, Nigeria', $rendered['html']);
        $this->assertStringContainsString('https://bamcomcrm.com/unsubscribe/token123', $rendered['html']);
    }

    /**
     * Test preview endpoint returns rendered content with sample variables.
     */
    public function test_preview_endpoint_returns_rendered_html_and_plain(): void
    {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Hello {{ contact.first_name }}',
            'preheader' => 'Quick preview for {{ contact.first_name }}',
            'body_html' => '<p>Welcome {{ contact.first_name }} to {{ company.name }}!</p><a href="{{ unsubscribe_url }}">Unsubscribe</a>',
            'category' => EmailTemplateCategory::Marketing->value,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('email-templates.preview', $template), [
                'wrap_brand' => true,
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'subject',
            'preheader',
            'html',
            'plain',
            'variables_used',
        ]);

        $this->assertStringContainsString('BAMCOM', $response->json('html'));
        $this->assertStringContainsString('Babajide', $response->json('subject'));
    }

    /**
     * Test send test email endpoint queues test email with EmailMessageType::Test.
     */
    public function test_send_test_email_dispatches_email_message(): void
    {
        Queue::fake();

        $template = EmailTemplate::factory()->create([
            'name' => 'Newsletter Alpha',
            'subject' => 'Exclusive Real Estate Monthly Digest',
            'body_html' => '<h2>Market Highlights</h2><p>Here are the latest yields.</p><a href="{{ unsubscribe_url }}">Unsubscribe</a>',
            'category' => EmailTemplateCategory::Newsletter->value,
        ]);

        $testRecipient = 'qa-engineer@bamcomcrm.com';

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('email-templates.test', $template), [
                'recipient_email' => $testRecipient,
                'wrap_brand' => true,
            ]);

        $response->assertAccepted();
        $response->assertJsonPath('email.to_email', $testRecipient);
        $response->assertJsonPath('email.type', EmailMessageType::Test->value);

        $this->assertDatabaseHas('email_messages', [
            'to_email' => $testRecipient,
            'email_template_id' => $template->id,
            'type' => EmailMessageType::Test->value,
        ]);
    }

    /**
     * Test authorized user can upload email template image asset.
     */
    public function test_user_can_upload_template_image_asset(): void
    {
        Storage::fake('public');

        $fakeImage = UploadedFile::fake()->image('luxury-duplex-banner.jpg', 1200, 600);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('email-templates.upload-image'), [
                'image' => $fakeImage,
            ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'url',
            'path',
            'name',
            'size',
        ]);

        $path = $response->json('path');
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString('storage/email-assets', $response->json('url'));
    }

    /**
     * Test template renders and safely preserves images, CTA buttons, and social media icons.
     */
    public function test_template_renders_and_preserves_images_cta_buttons_and_social_icons(): void
    {
        $richHtml = '
            <h2>Grand Waterfront Launch for {{ contact.first_name }}</h2>
            <!-- Image Component -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr>
                <td align="center">
                  <a href="https://bamcomcrm.com/properties/villa">
                    <img src="https://images.unsplash.com/photo-1613490493576-7fde63acd811" alt="Waterfront Villa" width="600" style="max-width: 100%; border-radius: 8px;" />
                  </a>
                  <p>Lekki Phase 1 Waterfront Villa</p>
                </td>
              </tr>
            </table>

            <!-- CTA Link Button Component -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 20px auto;">
              <tr>
                <td align="center" bgcolor="#0284c7" style="border-radius: 8px;">
                  <a href="https://bamcomcrm.com/inspections/book" style="display: inline-block; padding: 12px 28px; color: #ffffff; background-color: #0284c7; text-decoration: none; border-radius: 8px;">
                    Schedule Site Inspection &rarr;
                  </a>
                </td>
              </tr>
            </table>

            <!-- Social Media Icons Component -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr>
                <td align="center">
                  <p>Connect with Bamcom Real Estate:</p>
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="padding: 0 6px;">
                        <a href="https://wa.me/2348002262662"><img src="/images/email-icons/whatsapp.svg" alt="WhatsApp" width="28" height="28" /></a>
                      </td>
                      <td style="padding: 0 6px;">
                        <a href="https://instagram.com/bamcomrealestate"><img src="/images/email-icons/instagram.svg" alt="Instagram" width="28" height="28" /></a>
                      </td>
                      <td style="padding: 0 6px;">
                        <a href="https://linkedin.com/company/bamcom-real-estate"><img src="/images/email-icons/linkedin.svg" alt="LinkedIn" width="28" height="28" /></a>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <p><a href="{{ unsubscribe_url }}">Unsubscribe</a></p>
        ';

        $template = EmailTemplate::factory()->create([
            'name' => 'Rich Media Showcase',
            'subject' => 'VIP Invitation for {{ contact.first_name }}',
            'body_html' => $richHtml,
            'category' => EmailTemplateCategory::Marketing->value,
        ]);

        $rendered = $this->templateService->render(
            htmlTemplate: $template->body_html,
            variables: [
                'contact' => ['first_name' => 'Babajide'],
                'unsubscribe_url' => 'https://bamcomcrm.com/unsubscribe',
            ],
            wrapWithBrand: true
        );

        // Assert Image is preserved and rendered
        $this->assertStringContainsString('<img src="https://images.unsplash.com/photo-1613490493576-7fde63acd811"', $rendered['html']);
        $this->assertStringContainsString('alt="Waterfront Villa"', $rendered['html']);

        // Assert Link Button is preserved and rendered
        $this->assertStringContainsString('href="https://bamcomcrm.com/inspections/book"', $rendered['html']);
        $this->assertStringContainsString('Schedule Site Inspection &rarr;', $rendered['html']);
        $this->assertStringContainsString('background-color: #0284c7', $rendered['html']);

        // Assert Social Media Icons are preserved and rendered
        $this->assertStringContainsString('/images/email-icons/whatsapp.svg', $rendered['html']);
        $this->assertStringContainsString('/images/email-icons/instagram.svg', $rendered['html']);
        $this->assertStringContainsString('/images/email-icons/linkedin.svg', $rendered['html']);
        $this->assertStringContainsString('https://wa.me/2348002262662', $rendered['html']);
        $this->assertStringContainsString('Connect with Bamcom Real Estate:', $rendered['html']);
    }
}
