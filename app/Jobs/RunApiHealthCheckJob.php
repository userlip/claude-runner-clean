<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\ScrappApi;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunApiHealthCheckJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(
        public Task $task,
        public ScrappApi $api,
        public string $skill,
    ) {}

    public function handle(): void
    {
        $prompt = $this->buildSkillPrompt();

        $message = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => $prompt,
        ]);

        $this->api->update([
            'last_tested_at' => now(),
            'last_test_result' => 'running',
        ]);

        RunClaudeMessageJob::dispatch($this->task, $message);
    }

    protected function buildSkillPrompt(): string
    {
        return match ($this->skill) {
            'scrappa-endpoint-testing' => "Run /scrappa-endpoint-testing for the {$this->api->name} API. ".
                "Route prefix: {$this->api->route_prefix}. ".
                'If you find issues that need code changes, create a PR.',

            'rapidapi-publishing' => "Run /rapidapi-publishing for {$this->api->name}. ".
                "RapidAPI slug: {$this->api->rapidapi_slug}. ".
                'You have full autonomy to update RapidAPI directly.',

            default => throw new \InvalidArgumentException("Unknown skill: {$this->skill}"),
        };
    }
}
