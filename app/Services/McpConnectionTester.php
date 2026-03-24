<?php

namespace App\Services;

use App\Jobs\TestMcpServerConnectionJob;
use App\Models\McpServer;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Throwable;

class McpConnectionTester
{
    /**
     * @return array{successful: bool, status: string, message: string}
     */
    public function test(McpServer $server): array
    {
        return match ($server->transport) {
            'command' => $this->testCommandTransport($server),
            'sse' => $this->testSseTransport($server),
            default => [
                'successful' => false,
                'status' => 'failed',
                'message' => "Unsupported transport [{$server->transport}].",
            ],
        };
    }

    public function queue(McpServer $server): void
    {
        TestMcpServerConnectionJob::dispatch($server);
    }

    /**
     * @param  iterable<int, McpServer>  $servers
     */
    public function queueMany(iterable $servers): void
    {
        foreach ($servers as $server) {
            $this->queue($server);
        }
    }

    /**
     * @return array{successful: bool, status: string, message: string}
     */
    private function testCommandTransport(McpServer $server): array
    {
        if (blank($server->command)) {
            return [
                'successful' => false,
                'status' => 'failed',
                'message' => 'Command transport requires a command.',
            ];
        }

        try {
            $process = Process::env($server->env_vars ?? [])
                ->timeout(10)
                ->start([
                    $server->command,
                    ...($server->args ?? []),
                ]);

            return $this->probeCommandProcess($process);
        } catch (Throwable $exception) {
            return [
                'successful' => false,
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{successful: bool, status: string, message: string}
     */
    private function probeCommandProcess(InvokedProcess $process): array
    {
        $probeDeadline = microtime(true) + 1.0;
        $sawRunning = false;

        while (microtime(true) < $probeDeadline) {
            if ($process->running()) {
                $sawRunning = true;
                usleep(100_000);

                continue;
            }

            $result = $process->wait();

            if ($result->successful()) {
                return [
                    'successful' => true,
                    'status' => 'success',
                    'message' => $sawRunning
                        ? 'Command transport is reachable and stayed running during the readiness probe.'
                        : (trim($result->output()) ?: 'Command transport responded successfully.'),
                ];
            }

            return [
                'successful' => false,
                'status' => 'failed',
                'message' => trim($result->errorOutput()) ?: trim($result->output()) ?: 'Command transport failed.',
            ];
        }

        $process->stop();

        return [
            'successful' => true,
            'status' => 'success',
            'message' => 'Command transport is reachable and stayed running during the readiness probe.',
        ];
    }

    /**
     * @return array{successful: bool, status: string, message: string}
     */
    private function testSseTransport(McpServer $server): array
    {
        if (blank($server->url)) {
            return [
                'successful' => false,
                'status' => 'failed',
                'message' => 'SSE transport requires a URL.',
            ];
        }

        try {
            $response = Http::withHeaders($server->headers ?? [])
                ->timeout(10)
                ->get($server->url);

            if ($response->successful()) {
                return [
                    'successful' => true,
                    'status' => 'success',
                    'message' => "SSE endpoint responded with HTTP {$response->status()}.",
                ];
            }

            return [
                'successful' => false,
                'status' => 'failed',
                'message' => "SSE endpoint responded with HTTP {$response->status()}.",
            ];
        } catch (Throwable $exception) {
            return [
                'successful' => false,
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }
    }
}
