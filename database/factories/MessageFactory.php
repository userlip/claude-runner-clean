<?php

namespace Database\Factories;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => fake()->paragraph(),
            'raw_output' => null,
            'tool_calls' => null,
            'tokens_in' => null,
            'tokens_out' => null,
            'cost_usd' => null,
        ];
    }

    public function queued(): static
    {
        return $this->state(['status' => MessageStatus::Queued]);
    }

    public function sent(): static
    {
        return $this->state(['status' => MessageStatus::Sent]);
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
                ['name' => 'Read', 'params' => ['file_path' => '/app/Models/User.php']],
                ['name' => 'Edit', 'params' => ['file_path' => '/app/Models/User.php', 'old_string' => 'foo', 'new_string' => 'bar']],
            ],
        ]);
    }
}
