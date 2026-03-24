<?php

namespace App\Services;

use App\Models\McpServer;
use InvalidArgumentException;

class McpServerExporter
{
    public function __construct(
        private readonly McpConfigService $configService,
    ) {}

    public function export(?string $targetPath = null): string
    {
        $path = $targetPath ?? $this->configService->path();
        $payload = $this->payload()['mcpServers'];
        $config = $this->configService->read($path);
        $config['mcpServers'] ??= [];

        foreach ($this->configService->managedServerNames($path) as $managedServerName) {
            unset($config['mcpServers'][$managedServerName]);
        }

        foreach ($payload as $name => $serverConfig) {
            $config['mcpServers'][$name] = $serverConfig;
        }

        $this->configService->write($config, $path);
        $this->configService->writeManagedServerNames(array_keys($payload), $path);

        return $path;
    }

    /**
     * @return array{mcpServers: array<string, array<string, mixed>>}
     */
    public function payload(): array
    {
        $servers = McpServer::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (McpServer $server): array => [
                $server->name => $this->transformServer($server),
            ])
            ->all();

        return ['mcpServers' => $servers];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformServer(McpServer $server): array
    {
        return match ($server->transport) {
            'command' => [
                'type' => 'stdio',
                'command' => $server->command,
                'args' => $server->args ?? [],
                'env' => $server->env_vars ?? [],
            ],
            'sse' => [
                'type' => 'sse',
                'url' => $server->url,
                'headers' => $server->headers ?? [],
            ],
            default => throw new InvalidArgumentException("Unsupported MCP transport [{$server->transport}]."),
        };
    }
}
