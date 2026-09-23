<?php

namespace App\Providers;

use App\Services\AI\AIOrchestrator;
use App\Services\AI\ContextBuilder;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\IntentClassifier;
use App\Services\AI\KnowledgeService;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\MockAIProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Tools\BookInspectionTool;
use App\Services\AI\Tools\CalculatePaymentPlanTool;
use App\Services\AI\Tools\EscalateToHumanTool;
use App\Services\AI\Tools\SearchInventoryTool;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\Property\PropertyIntelligenceService;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 1. Register ToolRegistry with whitelisted domain tools
        $this->app->singleton(ToolRegistry::class, function ($app): ToolRegistry {
            $registry = new ToolRegistry;
            $propertyIntelligence = $app->make(PropertyIntelligenceService::class);

            $registry->register(new SearchInventoryTool($propertyIntelligence));
            $registry->register(new BookInspectionTool);
            $registry->register(new EscalateToHumanTool);
            $registry->register(new CalculatePaymentPlanTool);

            return $registry;
        });

        // 2. Bind Provider-Agnostic AIProviderInterface
        $this->app->bind(AIProviderInterface::class, function ($app): AIProviderInterface {
            $providerKey = strtolower((string) config('ai.default_provider', 'gemini'));

            return match ($providerKey) {
                'gemini' => new GeminiProvider(config('ai.providers.gemini', [])),
                'openai' => new OpenAIProvider(config('ai.providers.openai', [])),
                default => new MockAIProvider(config('ai.providers.mock', [])),
            };
        });

        // 3. Register IntentClassifier
        $this->app->singleton(IntentClassifier::class, function ($app): IntentClassifier {
            return new IntentClassifier($app->make(AIProviderInterface::class));
        });

        // 4. Register KnowledgeService
        $this->app->singleton(KnowledgeService::class, function ($app): KnowledgeService {
            return new KnowledgeService($app->make(PropertyIntelligenceService::class));
        });

        // 5. Register ContextBuilder
        $this->app->singleton(ContextBuilder::class, function ($app): ContextBuilder {
            return new ContextBuilder($app->make(KnowledgeService::class));
        });

        // 6. Register Central AIOrchestrator
        $this->app->singleton(AIOrchestrator::class, function ($app): AIOrchestrator {
            return new AIOrchestrator(
                provider: $app->make(AIProviderInterface::class),
                contextBuilder: $app->make(ContextBuilder::class),
                intentClassifier: $app->make(IntentClassifier::class),
                knowledgeService: $app->make(KnowledgeService::class),
                toolRegistry: $app->make(ToolRegistry::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
