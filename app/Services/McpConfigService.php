<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class McpConfigService
{
    public function path(): string
    {
        return config('mcp.export_path');
    }

    /**
     * @return array<string, mixed>
     */
    public function read(?string $path = null): array
    {
        $path ??= $this->path();

        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function write(array $config, ?string $path = null): string
    {
        $path ??= $this->path();

        File::ensureDirectoryExists(dirname($path), 0700, true);

        File::put(
            $path,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        chmod($path, 0600);

        return $path;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function servers(?string $path = null): array
    {
        $servers = $this->read($path)['mcpServers'] ?? [];

        return is_array($servers) ? $servers : [];
    }

    /**
     * @param  array<string, mixed>  $serverConfig
     */
    public function putServer(string $name, array $serverConfig, ?string $path = null): string
    {
        $config = $this->read($path);
        $config['mcpServers'] ??= [];
        $config['mcpServers'][$name] = $serverConfig;

        return $this->write($config, $path);
    }

    public function forgetServer(string $name, ?string $path = null): string
    {
        $config = $this->read($path);
        $config['mcpServers'] ??= [];
        unset($config['mcpServers'][$name]);

        return $this->write($config, $path);
    }

    /**
     * @param  array<string, array<string, mixed>>  $fallbackServers
     */
    public function jsonForCli(array $fallbackServers = []): ?string
    {
        $config = $this->read();

        if (! empty($config)) {
            return json_encode($config);
        }

        if (empty($fallbackServers)) {
            return null;
        }

        return json_encode(['mcpServers' => $fallbackServers]);
    }

    /**
     * @return array<int, string>
     */
    public function managedServerNames(?string $path = null): array
    {
        $statePath = $this->managedStatePath($path);

        if (! File::exists($statePath)) {
            return [];
        }

        $decoded = json_decode(File::get($statePath), true);

        if (! is_array($decoded) || ! isset($decoded['managed_servers']) || ! is_array($decoded['managed_servers'])) {
            return [];
        }

        return array_values(array_filter($decoded['managed_servers'], 'is_string'));
    }

    /**
     * @param  array<int, string>  $names
     */
    public function writeManagedServerNames(array $names, ?string $path = null): void
    {
        $statePath = $this->managedStatePath($path);

        File::ensureDirectoryExists(dirname($statePath), 0700, true);

        File::put(
            $statePath,
            json_encode(['managed_servers' => array_values($names)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        chmod($statePath, 0600);
    }

    public function managedStatePath(?string $path = null): string
    {
        $path ??= $this->path();

        if ($path === $this->path()) {
            return config('mcp.managed_state_path');
        }

        return dirname($path).'/.mcp-managed-servers.json';
    }
}
