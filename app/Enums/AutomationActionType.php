<?php

namespace App\Enums;

enum AutomationActionType: string
{
    case SendWhatsApp = 'SEND_WHATSAPP';
    case CreateTask = 'CREATE_TASK';
    case AssignAgent = 'ASSIGN_AGENT';
    case ChangePipelineStage = 'CHANGE_PIPELINE_STAGE';
    case AddTag = 'ADD_TAG';
    case RemoveTag = 'REMOVE_TAG';
    case UpdateLeadScore = 'UPDATE_LEAD_SCORE';
    case SendNotification = 'SEND_NOTIFICATION';
    case RequestHuman = 'REQUEST_HUMAN';

    /**
     * Human-readable label for the action.
     */
    public function label(): string
    {
        return match ($this) {
            self::SendWhatsApp => 'Send WhatsApp Message',
            self::CreateTask => 'Create Sales Task',
            self::AssignAgent => 'Assign Representative',
            self::ChangePipelineStage => 'Change Pipeline Stage',
            self::AddTag => 'Add Tag',
            self::RemoveTag => 'Remove Tag',
            self::UpdateLeadScore => 'Update Lead Score',
            self::SendNotification => 'Send In-App Notification',
            self::RequestHuman => 'Trigger AI-to-Human Handover',
        };
    }

    /**
     * Lucide icon associated with action.
     */
    public function iconName(): string
    {
        return match ($this) {
            self::SendWhatsApp => 'MessageSquare',
            self::CreateTask => 'CheckSquare',
            self::AssignAgent => 'UserCheck',
            self::ChangePipelineStage => 'Layers',
            self::AddTag => 'Tag',
            self::RemoveTag => 'Tag',
            self::UpdateLeadScore => 'Flame',
            self::SendNotification => 'Bell',
            self::RequestHuman => 'Users',
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
