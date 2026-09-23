<?php

namespace App\Services\AI\Tools;

use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Security Sandbox and Tool Dispatcher.
 *
 * Ensures AI models can ONLY invoke pre-approved, strictly validated application tools.
 * Direct SQL statements, raw queries, and arbitrary database writes are explicitly blocked.
 */
class ToolRegistry
{
    /**
     * @var array<string, AIToolInterface>
     */
    protected array $tools = [];

    public function __construct(array $initialTools = [])
    {
        foreach ($initialTools as $tool) {
            $this->register($tool);
        }
    }

    /**
     * Register a validated application tool.
     */
    public function register(AIToolInterface $tool): self
    {
        $this->tools[$tool->getName()] = $tool;

        return $this;
    }

    /**
     * Retrieve tool by name.
     */
    public function get(string $name): ?AIToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Check if a tool is registered and authorized.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Get all registered tools.
     *
     * @return array<string, AIToolInterface>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Export tools formatted as schemas for LLM provider function calling.
     *
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getToolSchemas(): array
    {
        $schemas = [];

        foreach ($this->tools as $tool) {
            $schemas[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->getParametersSchema(),
            ];
        }

        return $schemas;
    }

    /**
     * Execute a tool with strict argument sanitation and security validation.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function execute(string $name, array $arguments, array $context = []): array
    {
        // 1. Verify Tool Exists in Whitelist
        if (! $this->has($name)) {
            Log::warning("AI Security Alert: Attempted execution of unregistered tool '{$name}'.", [
                'arguments' => $arguments,
                'context' => array_keys($context),
            ]);

            throw new InvalidArgumentException("Unauthorized or unrecognized AI action '{$name}'. Execution denied.");
        }

        // 2. Scan Arguments for Malicious SQL / Code Injection Patterns
        $this->assertNoArbitrarySqlInjection($arguments);

        $tool = $this->get($name);

        try {
            return $tool->execute($arguments, $context);
        } catch (\Throwable $e) {
            Log::error("Tool execution failed for '{$name}': {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => "Action '{$name}' could not be completed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Inspect arguments recursively to guarantee no raw SQL statements or schema alterations are passed.
     */
    protected function assertNoArbitrarySqlInjection(array $data): void
    {
        $dangerousPatterns = [
            '/\b(SELECT\s+.*\s+FROM|INSERT\s+INTO|UPDATE\s+.*\s+SET|DELETE\s+FROM|DROP\s+TABLE|ALTER\s+TABLE|TRUNCATE\s+TABLE|UNION\s+SELECT)\b/i',
            '/(--|;|\/\*|\*\/|xp_cmdshell|exec\s*\()/i',
        ];

        array_walk_recursive($data, function ($val) use ($dangerousPatterns): void {
            if (is_string($val)) {
                foreach ($dangerousPatterns as $pattern) {
                    if (preg_match($pattern, $val)) {
                        throw new InvalidArgumentException('Security Violation: Potential arbitrary SQL pattern detected in AI tool arguments. Action rejected.');
                    }
                }
            }
        });
    }
}
