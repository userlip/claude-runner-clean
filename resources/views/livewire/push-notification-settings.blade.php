<x-filament::section :aside="true" heading="Push Notifications" description="Receive browser notifications when tasks complete.">
    <div class="space-y-4">
        <!-- Toggle -->
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-gray-950 dark:text-white">Enable Push Notifications</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Get notified when Claude Code tasks complete or fail.</p>
            </div>
            <button
                type="button"
                role="switch"
                wire:click="toggleEnabled"
                aria-checked="{{ $enabled ? 'true' : 'false' }}"
                style="position: relative; display: inline-flex; height: 24px; width: 44px; flex-shrink: 0; cursor: pointer; border-radius: 9999px; border: 2px solid transparent; transition: background-color 200ms ease-in-out; background-color: {{ $enabled ? 'rgb(59, 130, 246)' : 'rgb(156, 163, 175)' }};"
            >
                <span
                    style="pointer-events: none; display: inline-block; height: 20px; width: 20px; border-radius: 9999px; background-color: white; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1); transition: transform 200ms ease-in-out; transform: {{ $enabled ? 'translateX(20px)' : 'translateX(0)' }};"
                ></span>
            </button>
        </div>

        @if($enabled)
        <div class="rounded-lg bg-success-50 dark:bg-success-500/10 p-4 text-success-700 dark:text-success-400">
            <div class="flex items-center gap-2">
                <x-heroicon-o-check-circle class="w-5 h-5" />
                <span>Push notifications are enabled. You'll be notified when tasks complete.</span>
            </div>
        </div>
        @endif
    </div>
</x-filament::section>
