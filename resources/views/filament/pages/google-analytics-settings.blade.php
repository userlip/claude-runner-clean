<x-filament-panels::page>
    @php $connections = $this->getConnections(); @endphp

    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div style="display: flex; flex-wrap: wrap; align-items: stretch; gap: 1rem;">
            <div style="flex: 2 1 34rem; min-width: 18rem; border-radius: 1.25rem; padding: 1.25rem 1.5rem; background: linear-gradient(140deg, rgba(59, 130, 246, 0.18), rgba(15, 23, 42, 0.88)); border: 1px solid rgba(148, 163, 184, 0.18);">
                <div style="display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 9999px; padding: 0.35rem 0.7rem; background: rgba(15, 23, 42, 0.55); color: rgb(191, 219, 254); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;">
                    Analytics Access
                </div>
                <h2 style="margin: 0.9rem 0 0.4rem; font-size: 1.35rem; font-weight: 700; color: white;">Service account connections for reports and property discovery</h2>
                <p style="margin: 0; max-width: 42rem; font-size: 0.92rem; line-height: 1.6; color: rgba(226, 232, 240, 0.82);">
                    This page should look like part of the admin panel, not a raw browser form. The connection flow below now uses Filament inputs and keeps the setup steps readable instead of dumping plain labels and file controls into a dark card.
                </p>
            </div>

            <div style="flex: 1 1 16rem; min-width: 14rem; display: grid; gap: 0.85rem;">
                <div style="border-radius: 1rem; padding: 1rem 1.1rem; background: rgba(15, 23, 42, 0.72); border: 1px solid rgba(148, 163, 184, 0.12);">
                    <p style="margin: 0; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: rgb(148, 163, 184);">Connected</p>
                    <p style="margin: 0.35rem 0 0; font-size: 1.8rem; font-weight: 700; color: white;">{{ $connections->count() }}</p>
                    <p style="margin: 0.25rem 0 0; font-size: 0.82rem; color: rgb(148, 163, 184);">Google Analytics accounts ready for Claude</p>
                </div>

                <div style="border-radius: 1rem; padding: 1rem 1.1rem; background: rgba(15, 23, 42, 0.72); border: 1px solid rgba(148, 163, 184, 0.12);">
                    <p style="margin: 0; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: rgb(148, 163, 184);">Recommended</p>
                    <p style="margin: 0.35rem 0 0; font-size: 0.95rem; font-weight: 600; color: white;">Viewer access</p>
                    <p style="margin: 0.3rem 0 0; font-size: 0.82rem; line-height: 1.5; color: rgb(148, 163, 184);">Use a dedicated service account and add a default property ID when you have one.</p>
                </div>
            </div>
        </div>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                Setup Guide
            </x-slot>

            <x-slot name="description">
                How to create a Google Analytics service account
            </x-slot>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 0.9rem;">
                <div style="border-radius: 1rem; padding: 1rem; background: rgba(15, 23, 42, 0.48); border: 1px solid rgba(148, 163, 184, 0.12);">
                    <p style="margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgb(96, 165, 250);">Step 1</p>
                    <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: white;">Create a Google Cloud project</p>
                    <p style="margin: 0.45rem 0 0; font-size: 0.85rem; line-height: 1.55; color: rgb(148, 163, 184);">
                        Go to <a href="https://console.cloud.google.com/projectcreate" target="_blank" class="text-primary-600 dark:text-primary-400 underline">console.cloud.google.com</a> and create a new project or reuse an existing one.
                    </p>
                </div>

                <div style="border-radius: 1rem; padding: 1rem; background: rgba(15, 23, 42, 0.48); border: 1px solid rgba(148, 163, 184, 0.12);">
                    <p style="margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgb(96, 165, 250);">Step 2</p>
                    <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: white;">Enable the required APIs</p>
                    <ul style="margin: 0.5rem 0 0; padding-left: 1.1rem; font-size: 0.85rem; line-height: 1.6; color: rgb(148, 163, 184);">
                        <li><a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank" class="text-primary-600 dark:text-primary-400 underline">Google Analytics Data API</a></li>
                        <li><a href="https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com" target="_blank" class="text-primary-600 dark:text-primary-400 underline">Google Analytics Admin API</a></li>
                    </ul>
                </div>

                <div style="border-radius: 1rem; padding: 1rem; background: rgba(15, 23, 42, 0.48); border: 1px solid rgba(148, 163, 184, 0.12);">
                    <p style="margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgb(96, 165, 250);">Step 3</p>
                    <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: white;">Create the service account and key</p>
                    <p style="margin: 0.45rem 0 0; font-size: 0.85rem; line-height: 1.55; color: rgb(148, 163, 184);">
                        Use <a href="https://console.cloud.google.com/iam-admin/serviceaccounts/create" target="_blank" class="text-primary-600 dark:text-primary-400 underline">IAM &amp; Admin &gt; Service Accounts</a>, then create and download a JSON key from the <strong>Keys</strong> tab.
                    </p>
                </div>

                <div style="border-radius: 1rem; padding: 1rem; background: rgba(15, 23, 42, 0.48); border: 1px solid rgba(148, 163, 184, 0.12);">
                    <p style="margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgb(96, 165, 250);">Step 4</p>
                    <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: white;">Grant Analytics access</p>
                    <p style="margin: 0.45rem 0 0; font-size: 0.85rem; line-height: 1.55; color: rgb(148, 163, 184);">
                        In <a href="https://analytics.google.com" target="_blank" class="text-primary-600 dark:text-primary-400 underline">Google Analytics</a>, add the service account email in <strong>Admin &gt; Property Access Management</strong> with <strong>Viewer</strong> access.
                    </p>
                </div>

                <div style="border-radius: 1rem; padding: 1rem; background: rgba(15, 23, 42, 0.48); border: 1px solid rgba(148, 163, 184, 0.12); grid-column: 1 / -1;">
                    <p style="margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgb(96, 165, 250);">Step 5</p>
                    <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: white;">Optional: add a default property ID</p>
                    <p style="margin: 0.45rem 0 0; font-size: 0.85rem; line-height: 1.6; color: rgb(148, 163, 184);">
                        Find it in <strong>Admin &gt; Property Settings</strong> in Analytics. It looks like <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-white/10">properties/123456789</code>.
                    </p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Connected Accounts
            </x-slot>

            <x-slot name="description">
                Google Analytics service accounts available to Claude
            </x-slot>

            @if($connections->isEmpty())
                <div style="display: flex; align-items: center; gap: 1rem; border-radius: 1rem; padding: 1rem 1.1rem; background: rgba(15, 23, 42, 0.4); border: 1px dashed rgba(148, 163, 184, 0.18);">
                    <div style="display: flex; height: 3rem; width: 3rem; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 9999px; background: rgba(59, 130, 246, 0.12); color: rgb(96, 165, 250);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="width: 1.35rem; height: 1.35rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.181 8.68a4.503 4.503 0 0 1 1.903 6.405m-9.768-2.782L3.56 14.06a4.5 4.5 0 0 0 6.364 6.364l3.75-3.75m-6-6 6-6m2.121 2.121L17.56 4.94a4.5 4.5 0 0 0-6.364-6.364l-3.75 3.75" />
                        </svg>
                    </div>
                    <div>
                        <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: white;">No Connections</p>
                        <p style="margin: 0.3rem 0 0; font-size: 0.85rem; color: rgb(148, 163, 184);">Add a service account below to make Analytics properties available to Claude.</p>
                    </div>
                </div>
            @else
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    @foreach($connections as $connection)
                        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; border-radius: 1rem; padding: 1rem 1.1rem; background: rgba(15, 23, 42, 0.44); border: 1px solid rgba(148, 163, 184, 0.12);">
                            <div style="display: flex; min-width: 0; align-items: center; gap: 0.85rem;">
                                <div style="display: flex; height: 2.6rem; width: 2.6rem; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 0.85rem; background: rgba(34, 197, 94, 0.15); color: rgb(74, 222, 128);">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1rem; height: 1rem;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div style="min-width: 0;">
                                    <p style="margin: 0; font-size: 0.95rem; font-weight: 600; color: white;">{{ $connection->name }}</p>
                                    <p style="margin: 0.25rem 0 0; font-size: 0.8rem; color: rgb(148, 163, 184); word-break: break-all;">
                                        {{ $connection->getClientEmail() ?? 'Unknown email' }}
                                        @if($connection->property_id)
                                            <span style="display: inline-block; margin-left: 0.45rem; border-radius: 9999px; padding: 0.15rem 0.5rem; background: rgba(59, 130, 246, 0.14); color: rgb(147, 197, 253); font-size: 0.72rem; font-weight: 600;">{{ $connection->property_id }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <x-filament::button
                                color="danger"
                                size="xs"
                                wire:click="deleteConnection({{ $connection->id }})"
                                wire:confirm="Remove this Google Analytics connection?"
                            >
                                Remove
                            </x-filament::button>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Add New Connection
            </x-slot>

            <x-slot name="description">
                Upload a service account key and optionally pin a default property.
            </x-slot>

            <form wire:submit="addConnection" style="display: flex; flex-direction: column; gap: 1rem;">
                <div style="border-radius: 1rem; padding: 1rem 1.1rem; background: rgba(17, 17, 20, 0.78); border: 1px solid rgba(255, 255, 255, 0.06);">
                    {{ $this->form }}
                </div>

                <div style="display: flex; justify-content: flex-start;">
                    <x-filament::button type="submit">
                        Add Connection
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>
</x-filament-panels::page>
