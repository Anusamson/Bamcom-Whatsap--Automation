<?php

namespace App\Enums;

/**
 * AI-to-human handover escalation triggers.
 */
enum HandoverTrigger: string
{
    case CustomerRequest = 'customer_request';
    case Negotiation = 'negotiation';
    case PaymentReadiness = 'payment_readiness';
    case Complaint = 'complaint';
    case HighLeadScore = 'high_lead_score';
    case AiUncertainty = 'ai_uncertainty';
    case UnsupportedRequest = 'unsupported_request';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::CustomerRequest => 'Customer Requested Human',
            self::Negotiation => 'Price Negotiation & Discounts',
            self::PaymentReadiness => 'Payment Readiness & Deposit',
            self::Complaint => 'Customer Complaint or Dispute',
            self::HighLeadScore => 'High Lead Score (VIP/Hot Lead)',
            self::AiUncertainty => 'AI Uncertainty / Low Confidence',
            self::UnsupportedRequest => 'Unsupported Request / Complex Inquiry',
        };
    }

    /**
     * Urgency priority for CRM follow-up task generation.
     */
    public function priority(): string
    {
        return match ($this) {
            self::PaymentReadiness, self::Complaint => 'urgent',
            self::CustomerRequest, self::Negotiation, self::HighLeadScore => 'high',
            self::AiUncertainty, self::UnsupportedRequest => 'normal',
        };
    }

    /**
     * Default customer acknowledgment message sent to WhatsApp thread.
     */
    public function customerAcknowledgment(): string
    {
        return match ($this) {
            self::PaymentReadiness => 'Thank you! Our finance and sales management team has been alerted that you are ready to make a payment. A senior consultant is reviewing your file and will provide verified account instructions immediately.',
            self::Negotiation => 'Thank you for your inquiry! A sales manager has been assigned to your conversation to discuss available discounts, payment flexibility, and current promotions directly with you.',
            self::Complaint => 'We take your experience very seriously. A customer relations manager has been prioritized to your conversation and will reach out to resolve this matter shortly.',
            self::CustomerRequest => "I have notified our sales team! A human representative has been assigned to your conversation and will respond shortly.\n\nIn the meantime, feel free to leave any specific property questions or preferred inspection dates here.",
            self::HighLeadScore => 'Welcome to Bamcom Properties! Based on your preferred requirements, a senior property consultant has been assigned to personally guide your acquisition and will be in touch shortly.',
            self::AiUncertainty, self::UnsupportedRequest => 'To ensure you receive the most accurate and verified details, I am connecting you with a senior property advisor who will review your request and reply shortly.',
        };
    }
}
