<?php

namespace App\DataObjects;

class RalphState
{
    public function __construct(
        public readonly string $prompt,
        public readonly ?array $prd,
        public readonly string $progress,
        public readonly string $guardrails,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            prompt: $data['prompt'],
            prd: $data['prd'],
            progress: $data['progress'],
            guardrails: $data['guardrails'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'prompt' => $this->prompt,
            'prd' => $this->prd,
            'progress' => $this->progress,
            'guardrails' => $this->guardrails,
        ];
    }

    public function getNextStory(): ?array
    {
        $unpassedStories = collect($this->prd['userStories'] ?? [])
            ->filter(fn ($story) => ($story['passes'] ?? false) === false)
            ->sortBy('priority')
            ->values();

        return $unpassedStories->first();
    }

    public function allStoriesPassed(): bool
    {
        return collect($this->prd['userStories'] ?? [])
            ->every(fn ($story) => ($story['passes'] ?? false) === true);
    }
}
