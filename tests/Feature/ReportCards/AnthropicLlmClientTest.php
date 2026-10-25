<?php

declare(strict_types=1);

use App\Services\Llm\AnthropicLlmClient;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmException;
use App\Services\Llm\LlmRequest;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
    $this->client = fn (bool $fallbacks = true): AnthropicLlmClient => new AnthropicLlmClient(
        app(Factory::class), 'test-key', 'claude-opus-5-5', 2048, 'low', $fallbacks, 30,
    );
    $this->request = new LlmRequest('Write a comment.', '{"first_name":"Ada"}');
});

it('calls the Messages API and returns the text with its token usage', function (): void {
    Http::fake(['api.anthropic.com/v1/messages' => Http::response([
        'model' => 'claude-opus-5-5',
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'thinking', 'thinking' => ''], ['type' => 'text', 'text' => '  Ada had a good term.  ']],
        'usage' => ['input_tokens' => 120, 'output_tokens' => 30],
    ])]);

    $response = ($this->client)()->complete($this->request);

    expect($response->text)->toBe('Ada had a good term.')
        ->and($response->model)->toBe('claude-opus-5-5')
        ->and($response->inputTokens)->toBe(120)
        ->and($response->outputTokens)->toBe(30);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.anthropic.com/v1/messages'
        && $request->hasHeader('x-api-key', 'test-key')
        && $request->hasHeader('anthropic-version', '2023-06-01')
        && $request->hasHeader('anthropic-beta', 'server-side-fallback-2026-07-01')
        && $request->data() === [
            'model' => 'claude-opus-5-5',
            'max_tokens' => 2048,
            'system' => 'Write a comment.',
            'messages' => [['role' => 'user', 'content' => '{"first_name":"Ada"}']],
            'output_config' => ['effort' => 'low'],
            'fallbacks' => 'default',
        ]);
});

it('leaves out the fallback beta when turned off', function (): void {
    Http::fake(['*' => Http::response(['content' => [['type' => 'text', 'text' => 'Ok.']], 'stop_reason' => 'end_turn'])]);

    ($this->client)(false)->complete($this->request);

    Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('anthropic-beta') && ! array_key_exists('fallbacks', $request->data()));
});

it('treats a refusal or a cut-off answer as an error', function (array $body): void {
    Http::fake(['*' => Http::response($body)]);

    expect(fn () => ($this->client)()->complete($this->request))->toThrow(LlmException::class);
})->with([
    'refusal' => [['stop_reason' => 'refusal', 'content' => []]],
    'max tokens' => [['stop_reason' => 'max_tokens', 'content' => [['type' => 'text', 'text' => 'Ada has']]]],
    'no text' => [['stop_reason' => 'end_turn', 'content' => [['type' => 'thinking', 'thinking' => '']]]],
]);

it('retries overloaded responses, then gives up', function (): void {
    Http::fake(['*' => Http::sequence()->push([], 529)->push([], 529)->push([], 529)]);

    expect(fn () => ($this->client)()->complete($this->request))->toThrow(LlmException::class, 'could not be reached');
    Http::assertSentCount(3);
});

it('does not retry a bad request', function (): void {
    Http::fake(['*' => Http::response(['error' => ['type' => 'invalid_request_error']], 400)]);

    expect(fn () => ($this->client)()->complete($this->request))->toThrow(LlmException::class);
    Http::assertSentCount(1);
});

it('uses the fake without an API key', function (): void {
    config(['services.anthropic.key' => null]);
    app()->forgetInstance(LlmClient::class);

    expect(app(LlmClient::class))->toBeInstanceOf(FakeLlmClient::class);

    config(['services.anthropic.key' => 'sk-test']);
    app()->forgetInstance(LlmClient::class);

    expect(app(LlmClient::class))->toBeInstanceOf(AnthropicLlmClient::class);
});
