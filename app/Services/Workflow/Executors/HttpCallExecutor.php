<?php

declare(strict_types=1);

namespace App\Services\Workflow\Executors;

use App\Exceptions\StepExecutionException;
use App\Models\StepRun;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class HttpCallExecutor implements StepExecutorInterface
{
    public function execute(StepRun $stepRun, array $previousOutputs): array
    {
        $config = is_array($stepRun->input) ? $stepRun->input : [];
        $url = $config['url'] ?? null;
        $method = strtoupper((string) ($config['method'] ?? 'GET'));
        $payload = is_array($config['payload'] ?? null) ? $config['payload'] : [];

        if (! is_string($url) || $url === '') {
            throw new StepExecutionException('HTTP_CALL step requires a URL.');
        }

        try {
            $response = Http::timeout((int) config('workflow.http_timeout', 30))
                ->send($method, $url, ['json' => $payload])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new StepExecutionException($exception->getMessage(), previous: $exception);
        }

        return [
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ];
    }
}
