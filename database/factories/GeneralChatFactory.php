<?php

namespace Database\Factories;

use App\Enums\GeneralChatStatus;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GeneralChatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'user_id' => User::factory(),
            'ai_provider_id' => null, // Will use default in model boot
            'session_id' => Str::uuid(),
            'title' => fake()->optional()->sentence(3),
            'working_directory' => '/home/ploi',
            'status' => GeneralChatStatus::Pending,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state([
            'status' => GeneralChatStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => GeneralChatStatus::Completed,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => GeneralChatStatus::Failed,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
    }

    public function withTitle(string $title): static
    {
        return $this->state(['title' => $title]);
    }

    public function withProvider(AiProvider $provider): static
    {
        return $this->state(['ai_provider_id' => $provider->id]);
    }
}
