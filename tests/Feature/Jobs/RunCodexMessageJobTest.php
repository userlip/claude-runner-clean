<?php

use App\Jobs\RunCodexMessageJob;
use App\Models\Message;
use App\Models\Site;
use App\Models\Task;

test('captureProcessDiagnostics returns stderr and exit code for codex job', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);
    $job = new RunCodexMessageJob($task, $message);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open("bash -lc 'echo codex-boom >&2; exit 23'", $descriptors, $pipes, base_path());

    expect(is_resource($process))->toBeTrue();

    fclose($pipes[0]);

    $method = new ReflectionMethod($job, 'captureProcessDiagnostics');
    $method->setAccessible(true);

    $diagnostics = $method->invoke($job, $process, $pipes, false);

    expect($diagnostics['exit_code'])->toBe(23)
        ->and($diagnostics['error_output'])->toContain('codex-boom');
});
