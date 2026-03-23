<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div style="display: flex; flex-wrap: wrap; align-items: stretch; gap: 1rem;">
            <div style="flex: 2 1 34rem; min-width: 18rem; border-radius: 1.25rem; padding: 1.25rem 1.5rem; background: linear-gradient(140deg, rgba(168, 85, 247, 0.18), rgba(15, 23, 42, 0.88)); border: 1px solid rgba(148, 163, 184, 0.18);">
                <div style="display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 9999px; padding: 0.35rem 0.7rem; background: rgba(15, 23, 42, 0.55); color: rgb(216, 180, 254); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;">
                    Project Management
                </div>
                <h2 style="margin: 0.9rem 0 0.4rem; font-size: 1.35rem; font-weight: 700; color: white;">Connect your Asana workspace</h2>
                <p style="margin: 0; max-width: 42rem; font-size: 0.92rem; line-height: 1.6; color: rgba(226, 232, 240, 0.82);">
                    Link your Asana account using a Personal Access Token (PAT) to view and manage projects, tasks, and boards directly from Claude Runner.
                </p>
            </div>

            <div style="flex: 1 1 16rem; min-width: 14rem; display: grid; gap: 0.85rem;">
                <div style="border-radius: 1rem; padding: 1rem 1.1rem; background: rgba(15, 23, 42, 0.72); border: 1px solid rgba(148, 163, 184, 0.12);">
                    <p style="margin: 0; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: rgb(148, 163, 184);">Status</p>
                    @if($this->isConnected())
                        <p style="margin: 0.35rem 0 0; font-size: 1.4rem; font-weight: 700; color: rgb(74, 222, 128);">Connected</p>
                        <p style="margin: 0.25rem 0 0; font-size: 0.82rem; color: rgb(148, 163, 184);">Asana is ready to use</p>
                    @else
                        <p style="margin: 0.35rem 0 0; font-size: 1.4rem; font-weight: 700; color: rgb(148, 163, 184);">Not Connected</p>
                        <p style="margin: 0.25rem 0 0; font-size: 0.82rem; color: rgb(148, 163, 184);">Enter your PAT below to connect</p>
                    @endif
                </div>

                <div style="border-radius: 1rem; padding: 1rem 1.1rem; background: rgba(15, 23, 42, 0.72); border: 1px solid rgba(148, 163, 184, 0.12);">
                    <p style="margin: 0; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: rgb(148, 163, 184);">Workspaces</p>
                    <p style="margin: 0.35rem 0 0; font-size: 1.8rem; font-weight: 700; color: white;">{{ count($workspaces) }}</p>
                    <p style="margin: 0.25rem 0 0; font-size: 0.82rem; color: rgb(148, 163, 184);">Available workspaces</p>
                </div>
            </div>
        </div>

        @if($this->isConnected())
            <x-filament::section>
                <x-slot name="heading">
                    Default Workspace
                </x-slot>

                <x-slot name="description">
                    Select the workspace to use by default for Asana features.
                </x-slot>

                @if(count($workspaces) > 0)
                    <form wire:submit="saveWorkspace" style="display: flex; flex-direction: column; gap: 1rem;">
                        <div>
                            <label for="workspace-select" class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white" style="display: block; margin-bottom: 0.5rem;">Workspace</label>
                            <select
                                id="workspace-select"
                                wire:model="defaultWorkspaceId"
                                class="fi-select-input w-full rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm transition dark:border-white/10 dark:bg-white/5 dark:text-white"
                                style="padding: 0.5rem 0.75rem;"
                            >
                                @foreach($workspaces as $workspace)
                                    <option value="{{ $workspace['gid'] }}">{{ $workspace['name'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div style="display: flex; gap: 0.75rem;">
                            <x-filament::button type="submit">
                                Save Workspace
                            </x-filament::button>

                            <x-filament::button
                                color="danger"
                                wire:click="disconnect"
                                wire:confirm="Disconnect your Asana account? This will remove your PAT."
                            >
                                Disconnect
                            </x-filament::button>
                        </div>
                    </form>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">No workspaces found. Your PAT may need workspace access.</p>
                @endif
            </x-filament::section>
        @else
            <x-filament::section collapsible collapsed>
                <x-slot name="heading">
                    Setup Guide
                </x-slot>

                <x-slot name="description">
                    How to create an Asana Personal Access Token
                </x-slot>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 0.9rem;">
                    <div style="border-radius: 1rem; padding: 1rem; background: rgba(15, 23, 42, 0.48); border: 1px solid rgba(148, 163, 184, 0.12);">
                        <p style="margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgb(168, 85, 247);">Step 1</p>
                        <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: white;">Open Asana Developer Console</p>
                        <p style="margin: 0.45rem 0 0; font-size: 0.85rem; line-height: 1.55; color: rgb(148, 163, 184);">
                            Go to <a href="https://app.asana.com/0/my-apps" target="_blank" class="text-primary-600 dark:text-primary-400 underline">My Apps</a> in your Asana account settings.
                        </p>
                    </div>

                    <div style="border-radius: 1rem; padding: 1rem; background: rgba(15, 23, 42, 0.48); border: 1px solid rgba(148, 163, 184, 0.12);">
                        <p style="margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgb(168, 85, 247);">Step 2</p>
                        <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: white;">Create a Personal Access Token</p>
                        <p style="margin: 0.45rem 0 0; font-size: 0.85rem; line-height: 1.55; color: rgb(148, 163, 184);">
                            Click <strong>Create new token</strong>, give it a descriptive name like "Claude Runner", and copy the token.
                        </p>
                    </div>

                    <div style="border-radius: 1rem; padding: 1rem; background: rgba(15, 23, 42, 0.48); border: 1px solid rgba(148, 163, 184, 0.12);">
                        <p style="margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgb(168, 85, 247);">Step 3</p>
                        <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: white;">Paste it below</p>
                        <p style="margin: 0.45rem 0 0; font-size: 0.85rem; line-height: 1.55; color: rgb(148, 163, 184);">
                            Paste your token in the field below and click <strong>Connect</strong>. Your token is encrypted at rest.
                        </p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    Connect to Asana
                </x-slot>

                <x-slot name="description">
                    Enter your Personal Access Token to connect your Asana account.
                </x-slot>

                <form wire:submit="connect" style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label for="pat-input" class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white" style="display: block; margin-bottom: 0.5rem;">Personal Access Token</label>
                        <input
                            id="pat-input"
                            type="password"
                            wire:model="personalAccessToken"
                            placeholder="Enter your Asana PAT..."
                            class="fi-input w-full rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm transition dark:border-white/10 dark:bg-white/5 dark:text-white"
                            style="padding: 0.5rem 0.75rem;"
                        />
                        @error('personalAccessToken')
                            <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div style="display: flex; justify-content: flex-start;">
                        <x-filament::button type="submit">
                            Connect
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
