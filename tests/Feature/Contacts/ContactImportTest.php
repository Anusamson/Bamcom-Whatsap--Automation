<?php

namespace Tests\Feature\Contacts;

use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Permissions
        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => UserRole::Admin->value, 'guard_name' => 'web']);
        $adminRole->givePermissionTo([
            PermissionEnum::ContactsView->value,
            PermissionEnum::ContactsCreate->value,
            PermissionEnum::ContactsEdit->value,
            PermissionEnum::ContactsDelete->value,
        ]);

        $this->adminUser = User::factory()->create(['role' => UserRole::Admin]);
        $this->adminUser->assignRole($adminRole);

        // User without contacts create permission
        $this->regularUser = User::factory()->create(['role' => UserRole::SalesExecutive]);
    }

    public function test_guest_and_unauthorized_user_cannot_access_import(): void
    {
        // Guest
        $this->get(route('contacts.import.template'))->assertRedirect(route('login'));
        $this->postJson(route('contacts.import.preview'))->assertUnauthorized();

        // User without contacts.create permission
        $this->actingAs($this->regularUser)
            ->get(route('contacts.import.template'))
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->postJson(route('contacts.import.preview'))
            ->assertForbidden();
    }

    public function test_user_can_download_csv_template(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('contacts.import.template'));

        $response->assertOk();
        $this->assertStringContainsString('contacts_import_template', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('first_name,last_name,phone,email,location,occupation,lead_source,status,assigned_agent,notes', $response->streamedContent());
    }

    public function test_user_can_preview_csv_data(): void
    {
        $csvContent = "First Name,Last Name,Phone,Email,Location\n"
            ."Emeka,Okonkwo,08031234567,emeka@example.com,Abuja\n"
            ."Fatima,Bello,08129876543,fatima@example.com,Lagos\n";

        $file = UploadedFile::fake()->createWithContent('leads.csv', $csvContent);

        $response = $this->actingAs($this->adminUser)->postJson(route('contacts.import.preview'), [
            'source_type' => 'csv',
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'total_rows' => 2,
            ])
            ->assertJsonPath('headers.0', 'First Name')
            ->assertJsonPath('mapped_fields.0', 'first_name')
            ->assertJsonPath('sample_rows.0.first_name', 'Emeka')
            ->assertJsonPath('sample_rows.0.normalized_phone', '+2348031234567');
    }

    public function test_user_can_import_contacts_from_csv_with_phone_normalization(): void
    {
        $agent = User::factory()->create(['name' => 'Agent Chidi', 'email' => 'agent.chidi@bamcom.ng']);

        $csvContent = "First Name,Last Name,Phone,Email,Location,Occupation,Lead Source,Status,Assigned Agent Email,Notes\n"
            ."Emeka,Okonkwo,08031234567,emeka@example.com,Abuja,Architect,website,lead,agent.chidi@bamcom.ng,Looking for 4 bedroom duplex\n"
            ."Fatima,Bello,2348129876543,fatima@example.com,Lagos,Doctor,referral,prospect,,Prefers Ikoyi properties\n";

        $file = UploadedFile::fake()->createWithContent('leads.csv', $csvContent);

        $response = $this->actingAs($this->adminUser)->postJson(route('contacts.import.csv'), [
            'file' => $file,
            'duplicate_handling' => 'skip',
            'default_lead_source' => LeadSource::Website->value,
            'default_status' => ContactStatus::Lead->value,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'total_rows' => 2,
                'imported_count' => 2,
                'updated_count' => 0,
                'skipped_count' => 0,
            ]);

        // Verify normalized phone in database
        $this->assertDatabaseHas('contacts', [
            'first_name' => 'Emeka',
            'last_name' => 'Okonkwo',
            'phone' => '+2348031234567',
            'email' => 'emeka@example.com',
            'assigned_user_id' => $agent->id,
        ]);

        $this->assertDatabaseHas('contacts', [
            'first_name' => 'Fatima',
            'last_name' => 'Bello',
            'phone' => '+2348129876543',
            'email' => 'fatima@example.com',
        ]);

        // Verify notes created
        $contact = Contact::where('phone', '+2348031234567')->first();
        $this->assertNotNull($contact);
        $this->assertTrue($contact->notes()->where('content', 'Looking for 4 bedroom duplex')->exists());
    }

    public function test_duplicate_contacts_handling_skip_mode(): void
    {
        // Existing contact
        Contact::factory()->create([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'phone' => '+2348031234567',
            'email' => 'existing@example.com',
        ]);

        $csvContent = "First Name,Last Name,Phone,Email\n"
            ."UpdatedName,User,08031234567,existing@example.com\n"
            ."BrandNew,Customer,09011112222,brandnew@example.com\n";

        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent);

        $response = $this->actingAs($this->adminUser)->postJson(route('contacts.import.csv'), [
            'file' => $file,
            'duplicate_handling' => 'skip',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'total_rows' => 2,
                'imported_count' => 1,
                'skipped_count' => 1,
                'updated_count' => 0,
            ]);

        // Original contact was NOT updated
        $this->assertDatabaseHas('contacts', [
            'first_name' => 'Existing',
            'phone' => '+2348031234567',
        ]);
        $this->assertDatabaseMissing('contacts', [
            'first_name' => 'UpdatedName',
            'phone' => '+2348031234567',
        ]);
    }

    public function test_duplicate_contacts_handling_update_mode(): void
    {
        // Existing contact
        Contact::factory()->create([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'phone' => '+2348031234567',
            'email' => 'existing@example.com',
            'location' => 'Lagos',
        ]);

        $csvContent = "First Name,Last Name,Phone,Email,Location\n"
            ."UpdatedFirst,UpdatedLast,08031234567,existing@example.com,Abuja\n";

        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent);

        $response = $this->actingAs($this->adminUser)->postJson(route('contacts.import.csv'), [
            'file' => $file,
            'duplicate_handling' => 'update',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'total_rows' => 1,
                'imported_count' => 0,
                'updated_count' => 1,
                'skipped_count' => 0,
            ]);

        // Existing contact was updated
        $this->assertDatabaseHas('contacts', [
            'first_name' => 'UpdatedFirst',
            'last_name' => 'UpdatedLast',
            'phone' => '+2348031234567',
            'location' => 'Abuja',
        ]);
    }

    public function test_user_can_import_from_google_sheet_url(): void
    {
        $mockCsv = "First Name,Last Name,Phone,Email,Location\n"
            ."Tunde,Bakare,08023456789,tunde@example.com,Ibadan\n";

        Http::fake([
            'docs.google.com/*' => Http::response($mockCsv, 200, ['Content-Type' => 'text/csv']),
        ]);

        $sheetUrl = 'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit?usp=sharing';

        // Test preview
        $previewRes = $this->actingAs($this->adminUser)->postJson(route('contacts.import.preview'), [
            'source_type' => 'google_sheet',
            'sheet_url' => $sheetUrl,
        ]);

        $previewRes->assertOk()
            ->assertJson([
                'success' => true,
                'total_rows' => 1,
            ]);

        // Test import
        $importRes = $this->actingAs($this->adminUser)->postJson(route('contacts.import.google-sheet'), [
            'sheet_url' => $sheetUrl,
            'duplicate_handling' => 'skip',
            'default_lead_source' => LeadSource::Website->value,
            'default_status' => ContactStatus::Lead->value,
        ]);

        $importRes->assertOk()
            ->assertJson([
                'success' => true,
                'total_rows' => 1,
                'imported_count' => 1,
                'updated_count' => 0,
            ]);

        $this->assertDatabaseHas('contacts', [
            'first_name' => 'Tunde',
            'last_name' => 'Bakare',
            'phone' => '+2348023456789',
            'email' => 'tunde@example.com',
            'location' => 'Ibadan',
        ]);
    }

    public function test_google_sheet_with_private_access_returns_actionable_error(): void
    {
        // Google redirects private sheets to accounts.google.com ServiceLogin HTML page
        $privateLoginHtml = '<!DOCTYPE html><html><head><title>Google Accounts Sign in</title></head><body>Sign in with Google</body></html>';

        Http::fake([
            'docs.google.com/*' => Http::response($privateLoginHtml, 200, ['Content-Type' => 'text/html']),
        ]);

        $sheetUrl = 'https://docs.google.com/spreadsheets/d/private-sheet-id/edit';

        $response = $this->actingAs($this->adminUser)->postJson(route('contacts.import.google-sheet'), [
            'sheet_url' => $sheetUrl,
            'duplicate_handling' => 'skip',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sheet_url']);

        $this->assertStringContainsString('Anyone with the link can view', $response->json('message'));
    }

    public function test_import_with_invalid_google_sheet_url_fails_validation(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson(route('contacts.import.google-sheet'), [
            'sheet_url' => 'https://example.com/not-a-google-sheet',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sheet_url']);
    }
}
