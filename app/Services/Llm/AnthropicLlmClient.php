<?php

declare(strict_types=1);

namespace App\Services\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * The Anthropic Messages API through Laravel's HTTP client: one user turn, no tools, no
 * conversation history. Every value comes from config/services.php (env), never from a request.
 *
 * - `max_tokens` caps the cost of a single call; thinking counts towards it, so the cap is
 *   generous compared with a 2–3 sentence answer, and effort is kept low.
 * - With `fallbacks` on, a request the model declines is retried server-side on a fallback
 *   model inside the same call (beta header below). A refusal that remains is an error here.
 */
final class AnthropicLlmClient implements LlmClient
{
    private const VERSION = '2023-06-01';

    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function __construct(
        private readonly Http $http,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly string $effort,
        private readonly bool $fallbacks,
        private readonly int $timeoutSeconds,
        private readonly string $baseUrl = 'https://api.anthropic.com',
    ) {}

    public function complete(LlmRequest $request): LlmResponse
    {
        $headers = ['x-api-key' => $this->apiKey, 'anthropic-version' => self::VERSION];
        $body = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $request->system,
            'messages' => [['role' => 'user', 'content' => $request->prompt]],
            'output_config' => ['effort' => $this->effort],
        ];

        if ($this->fallbacks) {
            $headers['anthropic-beta'] = self::FALLBACK_BETA;
            $body['fallbacks'] = 'default';
        }

        try {
            $response = $this->http
                ->baseUrl($this->baseUrl)
                ->withHeaders($headers)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                // Overloaded, rate-limited or unreachable: try twice more, then give up.
                ->retry(3, 1000, fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503, 529], true)))
                ->post('/v1/messages', $body);
        } catch (ConnectionException|RequestException $e) {
            throw new LlmException('The language model could not be reached.', previous: $e);
        }

        /** @var array{content?: list<array{type: string, text?: string}>, stop_reason?: string|null, model?: string, usage?: array{input_tokens?: int, output_tokens?: int}} $json */
        $json = $response->json();

        if (($json['stop_reason'] ?? null) === 'refusal') {
            throw new LlmException('The language model declined to write a draft.');
        }

        $text = trim(implode('', array_map(
            fn (array $block): string => $block['type'] === 'text' ? ($block['text'] ?? '') : '',
            $json['content'] ?? [],
        )));

        if ($text === '' || ($json['stop_reason'] ?? null) === 'max_tokens') {
            throw new LlmException('The language model returned no complete text.');
        }

        return new LlmResponse(
            text: $text,
            model: $json['model'] ?? $this->model,
            inputTokens: $json['usage']['input_tokens'] ?? 0,
            outputTokens: $json['usage']['output_tokens'] ?? 0,
        );
    }
}
