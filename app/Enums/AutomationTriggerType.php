<?php

namespace App\Enums;

enum AutomationTriggerType: string
{
    case LeadScoreChanged = 'lead_score_changed';
    case PipelineStageChanged = 'pipeline_stage_changed';
    case InspectionScheduled = 'inspection_scheduled';
    case InspectionCompleted = 'inspection_completed';
    case InspectionCancelled = 'inspection_cancelled';
    case DealWon = 'deal_won';
    case DealLost = 'deal_lost';
    case ContactCreated = 'contact_created';
    case LeadCreated = 'lead_created';
    case WhatsAppMessageReceived = 'whatsapp_message_received';
    case Manual = 'manual';

    /**
     * Human-readable label for trigger event.
     */
    public function label(): string
    {
        return match ($this) {
            self::LeadScoreChanged => 'Lead Score Changed',
            self::PipelineStageChanged => 'Pipeline Stage Changed',
            self::InspectionScheduled => 'Site Inspection Scheduled',
            self::InspectionCompleted => 'Site Inspection Completed',
            self::InspectionCancelled => 'Site Inspection Cancelled',
            self::DealWon => 'Deal Closed Won',
            self::DealLost => 'Deal Marked Lost',
            self::ContactCreated => 'Contact Registered',
            self::LeadCreated => 'Lead Created',
            self::WhatsAppMessageReceived => 'WhatsApp Message Received',
            self::Manual => 'Manual / API Trigger',
        };
    }

    /**
     * Lucide icon associated with trigger.
     */
    public function iconName(): string
    {
        return match ($this) {
            self::LeadScoreChanged => 'Flame',
            self::PipelineStageChanged => 'Layers',
            self::InspectionScheduled, self::InspectionCompleted, self::InspectionCancelled => 'Compass',
            self::DealWon, self::DealLost => 'Handshake',
            self::ContactCreated => 'UserPlus',
            self::LeadCreated => 'Target',
            self::WhatsAppMessageReceived => 'MessageSquare',
            self::Manual => 'PlayCircle',
        };
    }

    /**
     * Get all string values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
