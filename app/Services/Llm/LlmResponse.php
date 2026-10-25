<?php

declare(strict_types=1);

namespace App\Services\Llm;

final readonly class LlmResponse
{
    public function __construct(
        public string $text,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}
