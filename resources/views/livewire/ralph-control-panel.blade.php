<x-filament::section label="Ralph Mode">
    @if(!$ralphEnabled)
        <div class="space-y-4">
            <p class="text-sm text-gray-600">
                Ralph Wiggum mode runs autonomous AI loops with fresh context each iteration.
                Progress persists via files instead of chat history.
            </p>

            <x-filament::form wire:submit="enableRalph">
                <div class="grid grid-cols-2 gap-4">
                    <x-filament::input
                        wire:model="ralphMaxIterations"
                        label="Max Iterations"
                        type="number"
                        placeholder="25"
                    />

                    <x-filament::select
                        wire:model="ralphRotationThreshold"
                        label="Rotate at Token %"
                        :options="[
                            '0.5' => '50%',
                            '0.7' => '70%',
                            '0.9' => '90%',
                        ]"
                    />
                </div>

                <x-filament::input
                    wire:model="ralphBranchName"
                    label="Branch Name"
                    placeholder="ralph/feature-name"
                />

                <x-filament::input
                    wire:model="verificationCommand"
                    label="Verification Command"
                    placeholder="php artisan test"
                />

                <h4 class="font-medium mt-4">User Stories</h4>

                <div wire:click="addStory" class="cursor-pointer text-sm text-primary-600">
                    + Add Story
                </div>

                @foreach($userStories as $index => $story)
                    <div class="border rounded p-3 space-y-2">
                        <x-filament::input
                            wire:model="userStories.{{ $index }}.id"
                            label="Story ID"
                            placeholder="US-001"
                        />
                        <x-filament::input
                            wire:model="userStories.{{ $index }}.title"
                            label="Title"
                            placeholder="Add login form"
                        />
                        <x-filament::input
                            wire:model="userStories.{{ $index }}.priority"
                            label="Priority"
                            type="number"
                        />
                    </div>
                @endforeach

                <x-filament::button type="submit">
                    Enable Ralph Mode
                </x-filament::button>
            </x-filament::form>
        </div>
    @else
        <div class="space-y-4">
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="font-medium mb-2">Ralph Loop Status</h4>

                @if(isset($ralphStatus['error']))
                    <p class="text-red-600">{{ $ralphStatus['error'] }}</p>
                @else
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Iteration:</span>
                            <span class="font-medium">{{ $ralphStatus['iteration'] ?? 0 }} / {{ $ralphStatus['max_iterations'] ?? 0 }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Stories:</span>
                            <span class="font-medium">{{ $ralphStatus['stories_passed'] ?? 0 }} / {{ $ralphStatus['stories_total'] ?? 0 }} passed</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Tokens Used:</span>
                            <span class="font-medium">{{ number_format($ralphStatus['tokens_used'] ?? 0) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Status:</span>
                            <span class="font-medium">{{ \Illuminate\Support\Str::headline($ralphStatus['status'] ?? 'unknown') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex gap-2">
                @if(in_array($ralphStatus['status'] ?? '', ['pending', 'running']))
                    <x-filament::button color="danger" wire:click="pauseRalph">
                        Pause
                    </x-filament::button>
                @else
                    <x-filament::button color="primary" wire:click="startRalph">
                        Start Ralph
                    </x-filament::button>
                @endif

                <x-filament::button color="gray" wire:click="disableRalph">
                    Disable Ralph
                </x-filament::button>
            </div>
        </div>
    @endif
</x-filament::section>
