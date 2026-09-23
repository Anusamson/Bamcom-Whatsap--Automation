<?php

namespace App\Services\AI\Providers;

use App\Enums\AIIntent;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\IntentResult;
use Closure;

/**
 * Mock AI provider for local testing and reliable test suites.
 */
class MockAIProvider implements AIProviderInterface
{
    /**
     * Optional custom response generator callback.
     */
    protected ?Closure $customResponseResolver = null;

    /**
     * Queue of deterministic responses for tests.
     *
     * @var list<AIResponse>
     */
    protected array $queuedResponses = [];

    /**
     * Recorded prompts received for test assertions.
     *
     * @var list<array<int, array{role: string, content: string}>>
     */
    public array $recordedPromptHistory = [];

    public function __construct(
        protected array $config = []
    ) {}

    /**
     * Override response generator with a test closure.
     */
    public function setResponseResolver(Closure $resolver): self
    {
        $this->customResponseResolver = $resolver;

        return $this;
    }

    /**
     * Set the next response to be returned by generateResponse.
     */
    public function setNextResponse(AIResponse $response): self
    {
        $this->queuedResponses = [$response];

        return $this;
    }

    /**
     * Queue a subsequent response for multi-turn tool calling tests.
     */
    public function setFollowupResponse(AIResponse $response): self
    {
        $this->queuedResponses[] = $response;

        return $this;
    }

    public function generateResponse(array $messages, array $options = []): AIResponse
    {
        $this->recordedPromptHistory[] = $messages;

        if (! empty($this->queuedResponses)) {
            return array_shift($this->queuedResponses);
        }

        if ($this->customResponseResolver) {
            return ($this->customResponseResolver)($messages, $options);
        }

        $lastMessage = end($messages);
        $userText = is_array($lastMessage) ? ($lastMessage['content'] ?? '') : '';

        // Check if options include tool definitions and prompt asks for search/inspection
        $toolCalls = [];
        if (! empty($options['tools'])) {
            $lower = strtolower($userText);
            if (str_contains($lower, 'inspection') || str_contains($lower, 'visit')) {
                $toolCalls[] = [
                    'name' => 'book_inspection',
                    'arguments' => [
                        'estate_name' => 'Oasis Heights Estate',
                        'date' => date('Y-m-d', strtotime('+3 days')),
                        'time' => '10:00 AM',
                        'notes' => 'Customer requested site inspection.',
                    ],
                ];
            } elseif (str_contains($lower, 'plot') || str_contains($lower, 'price') || str_contains($lower, 'available') || str_contains($lower, 'epe')) {
                $toolCalls[] = [
                    'name' => 'search_inventory',
                    'arguments' => [
                        'location' => 'Epe',
                        'in_stock_only' => true,
                        'limit' => 3,
                    ],
                ];
            }
        }

        $defaultReply = $this->config['default_reply']
            ?? 'Thank you for reaching out to Bamcom Properties. Our verified property inventory has available units in Epe and Ibeju-Lekki.';

        return new AIResponse(
            content: $defaultReply,
            toolCalls: $toolCalls,
            usage: ['prompt_tokens' => 120, 'completion_tokens' => 45, 'total_tokens' => 165],
            provider: 'mock',
            finishReason: ! empty($toolCalls) ? 'tool_calls' : 'stop',
            rawResponse: ['mock' => true]
        );
    }

    public function classifyIntent(string $message, array $candidateIntents = []): IntentResult
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'human') || str_contains($lower, 'agent') || str_contains($lower, 'speak with someone')) {
            return new IntentResult(AIIntent::HumanHandover, 0.98, method: 'mock');
        }

        if (str_contains($lower, 'inspection') || str_contains($lower, 'site visit')) {
            return new IntentResult(AIIntent::InspectionBooking, 0.95, ['date' => 'weekend'], method: 'mock');
        }

        if (str_contains($lower, 'price') || str_contains($lower, 'cost') || str_contains($lower, 'promo') || str_contains($lower, 'payment plan')) {
            return new IntentResult(AIIntent::PricingInquiry, 0.92, method: 'mock');
        }

        if (str_contains($lower, 'title') || str_contains($lower, 'c of o') || str_contains($lower, 'governor') || str_contains($lower, 'deed')) {
            return new IntentResult(AIIntent::TitleVerification, 0.90, method: 'mock');
        }

        if (str_contains($lower, 'hello') || str_contains($lower, 'hi') || str_contains($lower, 'good morning')) {
            return new IntentResult(AIIntent::Greeting, 0.95, method: 'mock');
        }

        return new IntentResult(AIIntent::PropertyInquiry, 0.85, method: 'mock');
    }

    public function getProviderName(): string
    {
        return 'mock';
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
