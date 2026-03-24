<?php

namespace App\Livewire\Settings;

use App\Models\McpServer;
use App\Services\McpConnectionTester;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Mcp extends Component
{
    use Toast;

    public bool $showAddForm = false;

    public bool $tableReady = true;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $serversForm = [];

    public string $newName = '';

    public string $newTransport = 'command';

    public string $newCommand = '';

    public string $newArgsText = '';

    public string $newUrl = '';

    public string $newHeadersText = '';

    public string $newEnvVarsText = '';

    public bool $newEnabled = true;

    /**
     * @var array<string, string>
     */
    public array $transportOptions = [
        'command' => 'Command (stdio)',
        'sse' => 'SSE URL',
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }

    public function addServer(): void
    {
        if (! $this->ensureTableReady()) {
            return;
        }

        $data = $this->validateServerPayload($this->newServerPayload());
        McpServer::create($data);

        $this->reset([
            'newName',
            'newCommand',
            'newArgsText',
            'newUrl',
            'newHeadersText',
            'newEnvVarsText',
        ]);

        $this->newTransport = 'command';
        $this->newEnabled = true;
        $this->showAddForm = false;

        $this->success('MCP server added.');
    }

    public function saveServer(int $id): void
    {
        if (! $this->ensureTableReady()) {
            return;
        }

        $server = McpServer::findOrFail($id);
        $server->update($this->validateServerPayload($this->serverPayload($server), $server->id));

        $this->success('MCP server saved.');
    }

    public function testServer(int $id): void
    {
        if (! $this->ensureTableReady()) {
            return;
        }

        $server = McpServer::findOrFail($id);
        $data = $this->validateServerPayload($this->serverPayload($server), $server->id);

        $draft = new McpServer([
            ...$server->attributesToArray(),
            ...$data,
        ]);

        $result = app(McpConnectionTester::class)->test($draft);

        if (! $this->hasUnsavedConnectionChanges($server, $data)) {
            $server->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => $result['status'],
                'last_test_message' => $result['message'],
            ])->save();
        }

        if ($result['successful']) {
            $this->success($result['message']);

            return;
        }

        $this->error($result['message']);
    }

    public function deleteServer(int $id): void
    {
        if (! $this->ensureTableReady()) {
            return;
        }

        McpServer::findOrFail($id)->delete();
        $this->success('MCP server deleted.');
    }

    public function render(): View
    {
        $this->tableReady = Schema::hasTable('mcp_servers');
        $servers = collect();

        if ($this->tableReady) {
            $servers = McpServer::query()
                ->orderBy('name')
                ->get();

            $servers->each(fn (McpServer $server) => $this->hydrateServerProperties($server));
        }

        return view('livewire.settings.mcp', [
            'servers' => $servers,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function newServerPayload(): array
    {
        return [
            'name' => $this->newName,
            'transport' => $this->newTransport,
            'command' => $this->newCommand,
            'args_text' => $this->newArgsText,
            'url' => $this->newUrl,
            'headers_text' => $this->newHeadersText,
            'env_vars_text' => $this->newEnvVarsText,
            'enabled' => $this->newEnabled,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serverPayload(McpServer $server): array
    {
        $form = $this->serversForm[$server->id] ?? [];

        return [
            'name' => $form['name'] ?? $server->name,
            'transport' => $form['transport'] ?? $server->transport,
            'command' => $form['command'] ?? $server->command,
            'args_text' => $form['args_text'] ?? $this->formatList($server->args ?? []),
            'url' => $form['url'] ?? $server->url,
            'headers_text' => $form['headers_text'] ?? $this->formatPairs($server->headers ?? []),
            'env_vars_text' => $form['env_vars_text'] ?? $this->formatPairs($server->env_vars ?? []),
            'enabled' => (bool) ($form['enabled'] ?? $server->enabled),
        ];
    }

    private function hydrateServerProperties(McpServer $server): void
    {
        $this->serversForm[$server->id] ??= [
            'name' => $server->name,
            'transport' => $server->transport,
            'command' => $server->command ?? '',
            'args_text' => $this->formatList($server->args ?? []),
            'url' => $server->url ?? '',
            'headers_text' => $this->formatPairs($server->headers ?? []),
            'env_vars_text' => $this->formatPairs($server->env_vars ?? []),
            'enabled' => (bool) $server->enabled,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateServerPayload(array $payload, ?int $serverId = null): array
    {
        Validator::make($payload, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('mcp_servers', 'name')->ignore($serverId),
            ],
            'transport' => ['required', Rule::in(array_keys($this->transportOptions))],
            'command' => [
                Rule::requiredIf(fn (): bool => $payload['transport'] === 'command'),
                'nullable',
                'string',
                'max:255',
            ],
            'url' => [
                Rule::requiredIf(fn (): bool => $payload['transport'] === 'sse'),
                'nullable',
                'url',
                'max:1000',
            ],
            'enabled' => ['required', 'boolean'],
        ])->validate();

        return $this->normalizePayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $transport = $payload['transport'];

        return [
            'name' => trim((string) $payload['name']),
            'transport' => $transport,
            'command' => $transport === 'command' ? trim((string) $payload['command']) : null,
            'args' => $transport === 'command' ? $this->parseList((string) ($payload['args_text'] ?? '')) : [],
            'url' => $transport === 'sse' ? trim((string) $payload['url']) : null,
            'headers' => $transport === 'sse' ? $this->parsePairs((string) ($payload['headers_text'] ?? '')) : [],
            'env_vars' => $transport === 'command' ? $this->parsePairs((string) ($payload['env_vars_text'] ?? '')) : [],
            'enabled' => (bool) $payload['enabled'],
        ];
    }

    /**
     * @param  array<int, string>  $values
     */
    private function formatList(array $values): string
    {
        return implode("\n", $values);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function formatPairs(array $values): string
    {
        return collect($values)
            ->map(fn (string $value, string $key): string => "{$key}={$value}")
            ->implode("\n");
    }

    /**
     * @return array<int, string>
     */
    private function parseList(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function parsePairs(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->mapWithKeys(function (string $line): array {
                $separator = Str::contains($line, '=') ? '=' : ':';
                [$key, $value] = array_pad(explode($separator, $line, 2), 2, '');

                return [trim($key) => trim($value)];
            })
            ->filter(fn (string $value, string $key): bool => $key !== '')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasUnsavedConnectionChanges(McpServer $server, array $data): bool
    {
        $draft = $server->replicate();
        $draft->syncOriginal();
        $draft->fill($data);

        return $draft->isDirty(['name', 'transport', 'command', 'args', 'url', 'headers', 'env_vars', 'enabled']);
    }

    private function ensureTableReady(): bool
    {
        if (Schema::hasTable('mcp_servers')) {
            return true;
        }

        $this->error('MCP setup is not complete. Run the latest database migrations first.');

        return false;
    }
}
