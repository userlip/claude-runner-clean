<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            GitHub Connection
        </x-slot>

        @if($this->isConnected())
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-success-100 dark:bg-success-900">
                    <x-heroicon-o-check class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-lg font-medium">Connected as {{ $this->getGitHubConnection()->github_username }}</p>
                    <p class="text-sm text-gray-500">Your GitHub account is connected and ready to sync repositories.</p>
                </div>
            </div>
        @else
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-link-slash class="h-6 w-6 text-gray-400" />
                </div>
                <div>
                    <p class="text-lg font-medium">Not Connected</p>
                    <p class="text-sm text-gray-500">Connect your GitHub account to sync and manage your repositories.</p>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
