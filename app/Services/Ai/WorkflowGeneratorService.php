<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\StepType;
use App\Exceptions\AiGenerationException;
use App\Exceptions\AiUnavailableException;
use App\Services\Workflow\DagParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class WorkflowGeneratorService
{
    public const SYSTEM_PROMPT = <<<'PROMPT'
You are a workflow definition expert for FlowForge platform.
Generate a valid workflow DAG as JSON based on user description.

Available step types and their config:
- HTTP_CALL: {"url": "string", "method": "GET|POST|PUT|DELETE", "headers": {}, "body": {}}
- CONDITION: {"expression": "string (use variables from previous step outputs)"}
- DELAY: {"seconds": number}
- SCRIPT: {"expression": "string (mathematical or logical expression)"}

Rules:
- Max 20 steps
- Step IDs must be unique slugs (lowercase, hyphens only)
- No circular dependencies
- Each step must have: id, type, name, config, dependencies (array)

Respond ONLY with valid JSON in this exact format, no other text:
{"steps": [...]}

Example 1:
User: "Fetch user data then send email notification"
Response: {"steps":[{"id":"fetch-user","type":"HTTP_CALL","name":"Fetch User Data","config":{"url":"https://api.example.com/users","method":"GET"},"dependencies":[]},{"id":"send-email","type":"HTTP_CALL","name":"Send Email Notification","config":{"url":"https://api.mailservice.com/send","method":"POST","body":{"template":"notification"}},"dependencies":["fetch-user"]}]}

Example 2:
User: "Get weather, check if temperature above 30, send alert webhook if yes"
Response: {"steps":[{"id":"get-weather","type":"HTTP_CALL","name":"Get Weather","config":{"url":"https://api.weather.com/current","method":"GET"},"dependencies":[]},{"id":"check-temp","type":"CONDITION","name":"Check Temperature","config":{"expression":"response.temperature > 30"},"dependencies":["get-weather"]},{"id":"send-alert","type":"HTTP_CALL","name":"Send Alert","config":{"url":"https://hooks.example.com/alert","method":"POST"},"dependencies":["check-temp"]}]}
PROMPT;

    private const FAILURE_KEY = 'ai_generation_consecutive_failures';

    public function __construct(private readonly DagParser $dagParser)
    {
    }

    public function generate(string $prompt, string $tenantId): array
    {
        if ((int) Cache::get(self::FAILURE_KEY, 0) >= 3) {
            throw new AiUnavailableException('AI feature temporarily unavailable');
        }

        $started = microtime(true);
        $trimmedPrompt = mb_substr($prompt, 0, 400);
        if ($trimmedPrompt !== $prompt) {
            Log::warning('AI workflow prompt truncated.', ['tenant_id' => $tenantId, 'prompt_length' => mb_strlen($prompt)]);
        }

        try {
            $definition = $this->requestDag($trimmedPrompt);
            $this->dagParser->parse($definition);
            Cache::forget(self::FAILURE_KEY);

            Log::info('AI workflow generation completed.', [
                'tenant_id' => $tenantId,
                'prompt_length' => mb_strlen($trimmedPrompt),
                'response_time_ms' => (int) round((microtime(true) - $started) * 1000),
                'validation_passed' => true,
            ]);

            return [
                'definition' => $definition,
                'confidence' => $this->confidence($definition),
            ];
        } catch (AiGenerationException $exception) {
            $this->recordFailure($tenantId, $trimmedPrompt, $started, false);
            throw $exception;
        } catch (Throwable $exception) {
            $this->recordFailure($tenantId, $trimmedPrompt, $started, false);
            throw new AiUnavailableException($exception->getMessage());
        }
    }

    private function requestDag(string $prompt): array
    {
        $content = $this->callProvider($prompt);
        $definition = $this->decodeDefinition($content);

        if ($definition !== null) {
            return $definition;
        }

        $retryContent = $this->callProvider("Return ONLY valid JSON:\n".$prompt);
        $retryDefinition = $this->decodeDefinition($retryContent);

        if ($retryDefinition === null) {
            throw new AiGenerationException('AI response was not valid JSON.', ['response' => 'invalid_json']);
        }

        return $retryDefinition;
    }

    private function callProvider(string $prompt): string
    {
        $apiKey = env('OPENAI_API_KEY') ?: env('AI_API_KEY');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new AiUnavailableException('AI API key is not configured.');
        }

        $response = Http::timeout(20)
            ->withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => env('AI_MODEL', 'GPT-5.4'),
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new AiUnavailableException('AI provider request failed.');
        }

        return (string) data_get($response->json(), 'choices.0.message.content', '');
    }

    private function decodeDefinition(string $content): ?array
    {
        try {
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded) || ! isset($decoded['steps']) || ! is_array($decoded['steps'])) {
            return null;
        }

        return $decoded;
    }

    private function confidence(array $definition): string
    {
        $steps = $definition['steps'] ?? [];
        if (! is_array($steps)) {
            return 'low';
        }

        $knownTypes = collect($steps)->every(fn (mixed $step): bool => is_array($step)
            && isset($step['type'])
            && StepType::tryFrom((string) $step['type']) !== null);

        if (count($steps) > 3 && $knownTypes) {
            return 'high';
        }

        return count($steps) >= 1 && count($steps) <= 3 && $knownTypes ? 'medium' : 'low';
    }

    private function recordFailure(string $tenantId, string $prompt, float $started, bool $validationPassed): void
    {
        Cache::put(self::FAILURE_KEY, (int) Cache::get(self::FAILURE_KEY, 0) + 1, now()->addMinutes(10));
        Log::warning('AI workflow generation failed.', [
            'tenant_id' => $tenantId,
            'prompt_length' => mb_strlen($prompt),
            'response_time_ms' => (int) round((microtime(true) - $started) * 1000),
            'validation_passed' => $validationPassed,
        ]);
    }
}
