<?php

namespace Tests\Feature\AI;

use App\Models\Contact;
use App\Models\Conversation;
use App\Services\AI\AIOrchestrator;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIFullPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_container_resolves_mock_provider_when_configured(): void
    {
        Config::set('ai.default_provider', 'mock');

        $provider = app(AIProviderInterface::class);

        $this->assertEquals('mock', $provider->getProviderName());
        $this->assertTrue($provider->isAvailable());
    }

    public function test_gemini_provider_with_mocked_http_responses(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Hello! Welcome to Bamcom Properties. How can I assist you with your land search today?'],
                            ],
                            'role' => 'model',
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 45,
                    'candidatesTokenCount' => 20,
                    'totalTokenCount' => 65,
                ],
            ], 200),
        ]);

        $provider = new GeminiProvider([
            'api_key' => 'fake_gemini_key',
            'model' => 'gemini-1.5-flash',
        ]);

        $response = $provider->generateResponse([
            ['role' => 'user', 'content' => 'Hello Bamcom'],
        ]);

        $this->assertEquals('gemini', $response->provider);
        $this->assertStringContainsString('Welcome to Bamcom Properties', $response->content);
        $this->assertEquals(65, $response->usage['total_tokens']);
    }

    public function test_openai_provider_with_mocked_http_responses(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'We have prime 500 SQM plots available at Oasis Heights, Epe with C of O.',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 30,
                    'completion_tokens' => 18,
                    'total_tokens' => 48,
                ],
            ], 200),
        ]);

        $provider = new OpenAIProvider([
            'api_key' => 'fake_openai_key',
            'model' => 'gpt-4o-mini',
        ]);

        $response = $provider->generateResponse([
            ['role' => 'user', 'content' => 'Do you have land in Epe?'],
        ]);

        $this->assertEquals('openai', $response->provider);
        $this->assertStringContainsString('Oasis Heights, Epe', $response->content);
        $this->assertEquals(48, $response->usage['total_tokens']);
    }

    public function test_orchestrator_end_to_end_using_gemini_provider_with_mocked_http(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'We have verified plots in Oasis Heights Estate with Certificate of Occupancy.'],
                            ],
                            'role' => 'model',
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 60,
                    'candidatesTokenCount' => 15,
                    'totalTokenCount' => 75,
                ],
            ], 200),
        ]);

        Config::set('ai.default_provider', 'gemini');
        Config::set('ai.providers.gemini.api_key', 'test_key');

        $orchestrator = app(AIOrchestrator::class);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->ai()->create(['contact_id' => $contact->id]);

        $response = $orchestrator->processConversationTurn(
            conversation: $conversation,
            incomingMessage: 'Tell me about Oasis Heights'
        );

        $this->assertStringContainsString('Oasis Heights Estate', $response->content);
        $this->assertEquals('gemini', $response->provider);
    }
}
