<?php

namespace App\Enums;

/**
 * Handling mode for CRM conversations.
 */
enum ConversationMode: string
{
    case Ai = 'ai';
    case Human = 'human';
    case Hybrid = 'hybrid';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ai => 'AI Agent',
            self::Human => 'Human Rep',
            self::Hybrid => 'Hybrid (AI + Human)',
        };
    }

    /**
     * Tailwind CSS badge styling.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Ai => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 border-purple-200 dark:border-purple-800',
            self::Human => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            self::Hybrid => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
        };
    }
}
