<?php

namespace App\Enums;

/**
 * Standardized customer intent categories for real estate automation.
 */
enum AIIntent: string
{
    case PropertyInquiry = 'property_inquiry';
    case PricingInquiry = 'pricing_inquiry';
    case InspectionBooking = 'inspection_booking';
    case TitleVerification = 'title_verification';
    case HumanHandover = 'human_handover';
    case GeneralFaq = 'general_faq';
    case Greeting = 'greeting';
    case Unknown = 'unknown';

    /**
     * Human-readable description of the intent.
     */
    public function label(): string
    {
        return match ($this) {
            self::PropertyInquiry => 'Property & Inventory Inquiry',
            self::PricingInquiry => 'Pricing, Payment Plan & Promos',
            self::InspectionBooking => 'Site Inspection Booking',
            self::TitleVerification => 'Legal Title & Documentation',
            self::HumanHandover => 'Speak to Human Representative',
            self::GeneralFaq => 'Company Info & General FAQ',
            self::Greeting => 'Greeting & Chit-Chat',
            self::Unknown => 'Unrecognized / General Query',
        };
    }

    /**
     * Check if this intent warrants automatic human takeover / alert.
     */
    public function requiresHumanEscalation(): bool
    {
        return match ($this) {
            self::HumanHandover => true,
            default => false,
        };
    }
}
