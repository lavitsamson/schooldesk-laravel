<?php

declare(strict_types=1);

namespace App\Services\Llm;

final readonly class LlmRequest
{
    public function __construct(
        public string $system,
        public string $prompt,
    ) {}

    /**
     * Identifies what was sent without keeping it: stored with each draft.
     */
    public function hash(string $model): string
    {
        return hash('sha256', $model."\n".$this->system."\n".$this->prompt);
    }
}
