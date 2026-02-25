<x-filament::section heading="Ralph Mode">
    @if(!$ralphEnabled)
        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Ralph Wiggum mode runs autonomous AI loops with fresh context each iteration.
                Progress persists via files instead of chat history.
            </p>

            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3">
                <h4 class="font-medium text-gray-900 dark:text-white">Import from GitHub</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Import child issues labeled <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">prd-slice</code> from a parent PRD issue.
                </p>
                <div class="flex gap-3 items-end">
                    <div class="grow">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">PRD Issue Number</label>
                        <x-filament::input.wrapper>
                            <x-filament::input
                                wire:model="importIssueNumber"
                                type="number"
                                placeholder="123"
                            />
                        </x-filament::input.wrapper>
                    </div>
                    <x-filament::button wire:click="importFromGitHub" color="gray">
                        Import from GitHub
                    </x-filament::button>
                </div>
            </div>

            <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white dark:bg-gray-900 px-3 text-sm text-gray-500 dark:text-gray-400">or configure manually</span>
                </div>
            </div>

            <form wire:submit="enableRalph" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Iterations</label>
                        <x-filament::input.wrapper>
                            <x-filament::input
                                wire:model="ralphMaxIterations"
                                type="number"
                                placeholder="25"
                            />
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rotate at Token %</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="ralphRotationThreshold">
                                <option value="0.5">50%</option>
                                <option value="0.7">70%</option>
                                <option value="0.9">90%</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Branch Name</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            wire:model="ralphBranchName"
                            type="text"
                            placeholder="ralph/feature-name"
                        />
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Verification Command</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            wire:model="verificationCommand"
                            type="text"
                            placeholder="php artisan test"
                        />
                    </x-filament::input.wrapper>
                </div>

                <h4 class="font-medium mt-4 text-gray-900 dark:text-white">User Stories</h4>

                <div wire:click="addStory" class="cursor-pointer text-sm text-primary-600 hover:text-primary-500">
                    + Add Story
                </div>

                @foreach($userStories as $index => $story)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Story ID</label>
                            <x-filament::input.wrapper>
                                <x-filament::input
                                    wire:model="userStories.{{ $index }}.id"
                                    type="text"
                                    placeholder="US-001"
                                />
                            </x-filament::input.wrapper>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                            <x-filament::input.wrapper>
                                <x-filament::input
                                    wire:model="userStories.{{ $index }}.title"
                                    type="text"
                                    placeholder="Add login form"
                                />
                            </x-filament::input.wrapper>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
                            <x-filament::input.wrapper>
                                <x-filament::input
                                    wire:model="userStories.{{ $index }}.priority"
                                    type="number"
                                />
                            </x-filament::input.wrapper>
                        </div>
                    </div>
                @endforeach

                <x-filament::button type="submit">
                    Enable Ralph Mode
                </x-filament::button>
            </form>
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
