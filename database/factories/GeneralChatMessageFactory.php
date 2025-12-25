<?php

namespace Database\Factories;

use App\Enums\MessageRole;
use App\Models\GeneralChat;
use Illuminate\Database\Eloquent\Factories\Factory;

class GeneralChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'general_chat_id' => GeneralChat::factory(),
            'role' => MessageRole::User,
            'content' => fake()->paragraph(),
            'raw_output' => null,
            'tool_calls' => null,
            'tokens_in' => null,
            'tokens_out' => null,
            'cost_usd' => null,
        ];
    }

    public function user(): static
    {
        return $this->state(['role' => MessageRole::User]);
    }

    public function assistant(): static
    {
        return $this->state(fn () => [
            'role' => MessageRole::Assistant,
            'tokens_in' => fake()->numberBetween(100, 1000),
            'tokens_out' => fake()->numberBetween(500, 5000),
            'cost_usd' => fake()->randomFloat(6, 0.001, 0.1),
        ]);
    }

    public function withToolCalls(): static
    {
        return $this->state([
            'tool_calls' => [
                ['name' => 'Read', 'input' => ['file_path' => '/home/ploi/.claude/settings.json']],
                ['name' => 'Edit', 'input' => ['file_path' => '/home/ploi/.claude/settings.json']],
            ],
        ]);
    }
}
