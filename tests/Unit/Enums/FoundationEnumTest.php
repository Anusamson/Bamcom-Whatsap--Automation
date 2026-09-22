<?php

namespace Tests\Unit\Enums;

use App\Enums\QueuePriority;
use App\Enums\ThemeMode;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Tests\TestCase;

class FoundationEnumTest extends TestCase
{
    public function test_user_role_enum_cases_and_labels(): void
    {
        $this->assertSame('Super Admin', UserRole::SuperAdmin->value);
        $this->assertSame('Admin', UserRole::Admin->value);
        $this->assertSame('Sales Manager', UserRole::SalesManager->value);
        $this->assertSame('Sales Executive', UserRole::SalesExecutive->value);
        $this->assertSame('Customer Support', UserRole::CustomerSupport->value);
        $this->assertSame('Marketing', UserRole::Marketing->value);
        $this->assertSame('Inspection Officer', UserRole::InspectionOfficer->value);
        $this->assertSame('Management', UserRole::Management->value);

        $this->assertSame('Super Admin', UserRole::SuperAdmin->label());
        $this->assertSame('Admin', UserRole::Admin->label());

        $this->assertCount(8, UserRole::values());
        $this->assertContains('Super Admin', UserRole::values());
        $this->assertContains('Admin', UserRole::values());

        $badge = UserRole::Admin->badgeClasses();
        $this->assertArrayHasKey('bg', $badge);
        $this->assertArrayHasKey('text', $badge);
        $this->assertArrayHasKey('border', $badge);
    }

    public function test_user_status_enum_cases_and_helpers(): void
    {
        $this->assertSame('active', UserStatus::Active->value);
        $this->assertSame('inactive', UserStatus::Inactive->value);
        $this->assertSame('pending', UserStatus::Pending->value);
        $this->assertSame('suspended', UserStatus::Suspended->value);

        $this->assertTrue(UserStatus::Active->isActive());
        $this->assertFalse(UserStatus::Suspended->isActive());

        $this->assertCount(4, UserStatus::values());
    }

    public function test_queue_priority_enum(): void
    {
        $this->assertSame('high', QueuePriority::High->value);
        $this->assertSame('default', QueuePriority::Default->value);
        $this->assertSame('low', QueuePriority::Low->value);

        $this->assertGreaterThan(QueuePriority::Default->weight(), QueuePriority::High->weight());
        $this->assertGreaterThan(QueuePriority::Low->weight(), QueuePriority::Default->weight());
    }

    public function test_theme_mode_enum(): void
    {
        $this->assertSame('light', ThemeMode::Light->value);
        $this->assertSame('dark', ThemeMode::Dark->value);
        $this->assertSame('system', ThemeMode::System->value);

        $this->assertSame('Light Mode', ThemeMode::Light->label());
        $this->assertSame('Dark Mode', ThemeMode::Dark->label());
    }
}
