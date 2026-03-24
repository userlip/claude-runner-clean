<div>
    <x-header title="MCP Servers" subtitle="Manage the shared Claude/Codex MCP config for the system user `ploi`." separator>
        <x-slot:actions>
            <x-button
                label="Settings"
                icon="o-adjustments-horizontal"
                link="{{ route('workbench.settings.index') }}"
                class="btn-ghost btn-sm"
            />
            @if($tableReady)
                <x-button
                    label="{{ $showAddForm ? 'Cancel' : 'Add Server' }}"
                    icon="{{ $showAddForm ? 'o-x-mark' : 'o-plus' }}"
                    wire:click="$toggle('showAddForm')"
                    class="{{ $showAddForm ? 'btn-ghost' : 'btn-primary' }} btn-sm"
                />
            @endif
        </x-slot:actions>
    </x-header>

    @if(! $tableReady)
        <x-card shadow class="mb-6">
            <x-header title="MCP setup is not complete" subtitle="The `mcp_servers` table is missing, so the workbench cannot load or edit shared MCP servers yet." size="text-lg" class="mb-4" separator />

            <div class="text-sm text-base-content/70">
                Run the latest database migrations, then reload this page.
            </div>
        </x-card>
    @endif

    @if($tableReady && $showAddForm)
        <x-card shadow class="mb-6">
            <x-header title="New MCP Server" subtitle="Add a stdio command or SSE endpoint to the shared workbench MCP config." size="text-lg" class="mb-4" separator />

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <x-input label="Server Name" wire:model="newName" placeholder="filesystem" required />
                <x-select label="Transport" wire:model.live="newTransport" :options="$transportOptions" required />
                <x-toggle label="Enabled" wire:model="newEnabled" />

                @if($newTransport === 'command')
                    <x-input label="Command" wire:model="newCommand" placeholder="npx" required />
                    <x-textarea
                        label="Arguments"
                        hint="One argument per line."
                        wire:model="newArgsText"
                        rows="5"
                        placeholder="-y&#10;@modelcontextprotocol/server-filesystem&#10;/srv/app"
                    />
                    <x-textarea
                        label="Environment Variables"
                        hint="One KEY=value pair per line."
                        wire:model="newEnvVarsText"
                        rows="5"
                        placeholder="ROOT_PATH=/srv/app"
                    />
                @else
                    <x-input label="SSE URL" wire:model="newUrl" placeholder="https://sentry.example.com/api/0/.../mcp" required />
                    <x-textarea
                        label="Headers"
                        hint="One KEY=value pair per line."
                        wire:model="newHeadersText"
                        rows="5"
                        placeholder="Authorization=Bearer token"
                    />
                @endif
            </div>

            <div class="flex gap-2 pt-4">
                <x-button label="Add Server" wire:click="addServer" spinner="addServer" class="btn-primary btn-sm" />
            </div>
        </x-card>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        @forelse($servers as $server)
            @php
                $transport = $serversForm[$server->id]['transport'] ?? $server->transport;
            @endphp
            <x-card shadow>
                <x-header size="text-lg" separator class="mb-4">
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <div class="size-8 rounded-lg bg-primary/10 flex items-center justify-center">
                                <x-icon name="o-server-stack" class="size-4 text-primary" />
                            </div>
                            <div>
                                {{ $server->name }}
                                <span class="badge badge-xs {{ $server->enabled ? 'badge-success' : 'badge-ghost' }} ml-1">
                                    {{ $server->enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                        </div>
                    </x-slot:title>
                    <x-slot:subtitle>
                        {{ strtoupper($server->transport) }}
                    </x-slot:subtitle>
                </x-header>

                <div class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <x-input label="Server Name" wire:model="serversForm.{{ $server->id }}.name" />
                        <x-select label="Transport" wire:model.live="serversForm.{{ $server->id }}.transport" :options="$transportOptions" />
                        <x-toggle label="Enabled" wire:model="serversForm.{{ $server->id }}.enabled" />

                        @if($transport === 'command')
                            <x-input label="Command" wire:model="serversForm.{{ $server->id }}.command" placeholder="npx" />
                            <x-textarea
                                label="Arguments"
                                hint="One argument per line."
                                wire:model="serversForm.{{ $server->id }}.args_text"
                                rows="5"
                            />
                            <x-textarea
                                label="Environment Variables"
                                hint="One KEY=value pair per line."
                                wire:model="serversForm.{{ $server->id }}.env_vars_text"
                                rows="5"
                            />
                        @else
                            <x-input label="SSE URL" wire:model="serversForm.{{ $server->id }}.url" />
                            <x-textarea
                                label="Headers"
                                hint="One KEY=value pair per line."
                                wire:model="serversForm.{{ $server->id }}.headers_text"
                                rows="5"
                            />
                        @endif
                    </div>

                    <div class="rounded-xl border border-base-300 bg-base-200/50 px-4 py-3 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">Last test:</span>
                            <span class="badge badge-sm {{ $server->last_test_status === 'success' ? 'badge-success' : ($server->last_test_status === 'failed' ? 'badge-error' : 'badge-ghost') }}">
                                {{ $server->last_test_status ? ucfirst($server->last_test_status) : 'Untested' }}
                            </span>
                            @if($server->last_tested_at)
                                <span class="text-base-content/60">{{ $server->last_tested_at->diffForHumans() }}</span>
                            @endif
                        </div>

                        @if($server->last_test_message)
                            <div class="mt-2 text-base-content/70">
                                {{ $server->last_test_message }}
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <x-button label="Save" wire:click="saveServer({{ $server->id }})" spinner="saveServer({{ $server->id }})" class="btn-primary btn-sm" />
                        <x-button label="Test" wire:click="testServer({{ $server->id }})" spinner="testServer({{ $server->id }})" class="btn-ghost btn-sm" />
                        <x-button
                            label="Delete"
                            wire:click="deleteServer({{ $server->id }})"
                            wire:confirm="Delete {{ $server->name }}? This removes it from the shared Claude/Codex MCP config."
                            class="btn-ghost btn-sm text-error"
                        />
                    </div>
                </div>
            </x-card>
        @empty
            <x-card shadow class="xl:col-span-2">
                <div class="py-10 text-center">
                    <div class="text-lg font-medium">No MCP servers configured</div>
                    <div class="text-base-content/60 mt-2">Add a server to make it available to the shared `ploi` Claude/Codex runtime.</div>
                </div>
            </x-card>
        @endforelse
    </div>
</div>
