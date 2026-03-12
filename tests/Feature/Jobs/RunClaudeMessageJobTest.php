<?php

use App\Exceptions\RateLimitException;
use App\Jobs\RunClaudeMessageJob;
use App\Models\AiProvider;
use App\Models\Message;
use App\Models\Site;
use App\Models\Task;

test('job constructor accepts task and message', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create([
        'task_id' => $task->id,
        'content' => 'echo "Hello"',
    ]);

    $job = new RunClaudeMessageJob($task, $message);

    expect($job->task->id)->toBe($task->id);
    expect($job->userMessage->content)->toBe('echo "Hello"');
    expect($job->continue)->toBeFalse();
});

test('job constructor accepts continue flag', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message, continue: true);

    expect($job->continue)->toBeTrue();
});

test('buildCommand includes session id', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create([
        'task_id' => $task->id,
        'content' => 'test prompt',
    ]);

    $job = new RunClaudeMessageJob($task, $message);
    $command = $job->buildCommand();

    expect($command)->toContain('--session-id');
    expect($command)->toContain('--output-format stream-json');
    expect($command)->toContain('-p');
});

test('buildCommand includes max turns when set', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->withMaxTurns(5)->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message);
    $command = $job->buildCommand();

    expect($command)->toContain('--max-turns 5');
});

test('buildCommand includes resume flag', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message, continue: true);
    $command = $job->buildCommand();

    expect($command)->toContain('--resume');
});

test('buildCommand escapes prompt correctly', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create([
        'task_id' => $task->id,
        'content' => "Hello 'world' with \"quotes\"",
    ]);

    $job = new RunClaudeMessageJob($task, $message);
    $command = $job->buildCommand();

    // The prompt should be escaped - verify it's shell-safe
    expect($command)->toContain('-p ');
    // Should use escapeshellarg which adds single quotes
    expect($command)->not->toContain("Hello 'world'");
});

test('job has correct timeout', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message);

    expect($job->timeout)->toBe(10800); // 3 hours for complex tasks
});

test('job has three tries for rate limit retries', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message);

    expect($job->tries)->toBe(3);
});

test('job has backoff intervals for rate limit retries', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message);

    // 30 minutes, 1 hour, 2 hours
    expect($job->backoff)->toBe([1800, 3600, 7200]);
});

test('it uses task provider env vars', function () {
    $provider = AiProvider::factory()->kimi()->create();
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create([
        'site_id' => $site->id,
        'ai_provider_id' => $provider->id,
    ]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message);

    $env = $job->getProviderEnvironment();

    expect($env)->toHaveKey('ANTHROPIC_BASE_URL')
        ->and($env['ANTHROPIC_MODEL'])->toBe('kimi-k2.5');
});

test('parseLine detects rate limit error with error field', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message);

    // Use reflection to access private parseLine method
    $method = new ReflectionMethod($job, 'parseLine');
    $method->setAccessible(true);

    $rateLimitLine = json_encode([
        'type' => 'assistant',
        'message' => [
            'content' => [
                ['type' => 'text', 'text' => "You've hit your limit · resets 6pm (UTC)"],
            ],
        ],
        'error' => 'rate_limit',
        'isApiErrorMessage' => true,
    ]);

    $result = $method->invoke($job, $rateLimitLine);

    expect($result)->toHaveKey('rate_limit');
    expect($result['rate_limit']['message'])->toContain("You've hit your limit");
    expect($result['rate_limit']['reset_time'])->toBe('6pm (UTC)');
    expect($result['rate_limit']['error_type'])->toBe('rate_limit');
});

test('parseLine detects rate limit error with isApiErrorMessage flag', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message);

    $method = new ReflectionMethod($job, 'parseLine');
    $method->setAccessible(true);

    // Some rate limit errors may only have isApiErrorMessage without the error field
    $rateLimitLine = json_encode([
        'type' => 'assistant',
        'message' => [
            'content' => [
                ['type' => 'text', 'text' => 'API rate limit exceeded'],
            ],
        ],
        'isApiErrorMessage' => true,
    ]);

    $result = $method->invoke($job, $rateLimitLine);

    expect($result)->toHaveKey('rate_limit');
    expect($result['rate_limit']['message'])->toBe('API rate limit exceeded');
});

test('parseLine does not flag normal assistant messages as rate limited', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message);

    $method = new ReflectionMethod($job, 'parseLine');
    $method->setAccessible(true);

    $normalLine = json_encode([
        'type' => 'assistant',
        'message' => [
            'content' => [
                ['type' => 'text', 'text' => 'Hello! How can I help you today?'],
            ],
        ],
    ]);

    $result = $method->invoke($job, $normalLine);

    expect($result)->not->toHaveKey('rate_limit');
    expect($result)->toHaveKey('content');
    expect($result['content'])->toBe('Hello! How can I help you today?');
});

test('captureProcessDiagnostics returns stderr and exit code', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);
    $job = new RunClaudeMessageJob($task, $message);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open("bash -lc 'echo boom >&2; exit 17'", $descriptors, $pipes, base_path());

    expect(is_resource($process))->toBeTrue();

    fclose($pipes[0]);

    $method = new ReflectionMethod($job, 'captureProcessDiagnostics');
    $method->setAccessible(true);

    $diagnostics = $method->invoke($job, $process, $pipes, false);

    expect($diagnostics['exit_code'])->toBe(17)
        ->and($diagnostics['error_output'])->toContain('boom');
});

test('RateLimitException stores reset time', function () {
    $exception = new RateLimitException(
        resetTime: '6pm (UTC)',
        message: 'Rate limit exceeded'
    );

    expect($exception->getMessage())->toBe('Rate limit exceeded');
    expect($exception->getResetDescription())->toBe('6pm (UTC)');
});

test('RateLimitException handles null reset time', function () {
    $exception = new RateLimitException(
        resetTime: null,
        message: 'Rate limit exceeded'
    );

    expect($exception->getResetDescription())->toBe('unknown time');
});
