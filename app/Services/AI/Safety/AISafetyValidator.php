<?php

namespace App\Services\AI\Safety;

use Illuminate\Support\Facades\Log;

/**
 * Validates AI-generated responses against safety, property integrity,
 * and business guardrails before dispatching to customers or presenting suggestions.
 */
class AISafetyValidator
{
    public const MAX_WHATSAPP_LENGTH = 4096;

    /**
     * Prohibited scam, speculative, and unauthorized financial guarantee phrases.
     *
     * @var array<int, string>
     */
    protected array $prohibitedPhrases = [
        'guaranteed return',
        'guaranteed profit',
        '100% return',
        'double your money',
        'risk-free investment',
        'crypto investment',
        'send to my personal account',
        'pay into my personal account',
        'personal bank account',
        'western union',
        'cash in hand to agent',
        'no documentation needed',
        'unofficial allocation',
        'fake survey',
    ];

    /**
     * Prohibited abusive or offensive terms.
     *
     * @var array<int, string>
     */
    protected array $abusiveKeywords = [
        'idiot',
        'stupid',
        'fool',
        'scam',
        'fraudulent company',
        'fuck',
        'bastard',
        'shut up',
    ];

    /**
     * Validate an AI-generated message string.
     *
     * @param  array<string, mixed>  $context
     * @return array{is_valid: bool, violations: array<int, string>, sanitized_content: string}
     */
    public function validate(string $content, array $context = []): array
    {
        $violations = [];
        $trimmed = trim($content);

        // 1. Channel length & non-empty validation
        if ($trimmed === '') {
            $violations[] = 'Generated content is empty.';
        } elseif (mb_strlen($trimmed) > self::MAX_WHATSAPP_LENGTH) {
            $violations[] = 'Content exceeds WhatsApp maximum length of '.self::MAX_WHATSAPP_LENGTH.' characters (actual: '.mb_strlen($trimmed).').';
        }

        $lower = mb_strtolower($trimmed);

        // 2. Business guardrails: Prohibited promises & financial guarantees
        foreach ($this->prohibitedPhrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                $violations[] = "Violated business integrity rule: contains prohibited phrase '{$phrase}'.";
            }
        }

        // 3. Safety guardrails: Abusive or offensive language
        foreach ($this->abusiveKeywords as $keyword) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'\b/i', $lower)) {
                $violations[] = "Violated content safety rule: contains inappropriate language '{$keyword}'.";
            }
        }

        // 4. Payment safety: Ensure payments are directed only to Bamcom corporate accounts
        if (str_contains($lower, 'transfer') || str_contains($lower, 'account number')) {
            if (str_contains($lower, 'personal') || str_contains($lower, 'individual')) {
                $violations[] = 'Violated payment safety rule: payment must strictly be directed to corporate accounts, never personal accounts.';
            }
        }

        $isValid = empty($violations);

        if (! $isValid) {
            Log::warning('AI Safety Validation failed', [
                'violations' => $violations,
                'content_snippet' => mb_substr($trimmed, 0, 100),
                'context' => $context,
            ]);
        }

        return [
            'is_valid' => $isValid,
            'violations' => $violations,
            'sanitized_content' => $trimmed,
        ];
    }
}
