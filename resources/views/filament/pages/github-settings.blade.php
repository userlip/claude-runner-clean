<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            GitHub Connection
        </x-slot>

        @if($this->isConnected())
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-success-100 dark:bg-success-900">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-success-600 dark:text-success-400" style="width: 1.5rem; height: 1.5rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <div>
                    <p class="text-lg font-medium">Connected as {{ $this->getGitHubConnection()->github_username }}</p>
                    <p class="text-sm text-gray-500">Your GitHub account is connected and ready to sync repositories.</p>
                </div>
            </div>
        @else
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-gray-400" style="width: 1.5rem; height: 1.5rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.181 8.68a4.503 4.503 0 0 1 1.903 6.405m-9.768-2.782L3.56 14.06a4.5 4.5 0 0 0 6.364 6.364l3.75-3.75m-6-6 6-6m2.121 2.121L17.56 4.94a4.5 4.5 0 0 0-6.364-6.364l-3.75 3.75" />
                    </svg>
                </div>
                <div>
                    <p class="text-lg font-medium">Not Connected</p>
                    <p class="text-sm text-gray-500">Connect your GitHub account to sync and manage your repositories.</p>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
