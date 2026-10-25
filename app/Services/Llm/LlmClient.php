<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * A single-turn text completion. The app depends on this interface only: AnthropicLlmClient
 * when an API key is configured, FakeLlmClient otherwise (local development, demo, tests).
 */
interface LlmClient
{
    /**
     * @throws LlmException when no usable text comes back (network error, refusal, cut off)
     */
    public function complete(LlmRequest $request): LlmResponse;
}
