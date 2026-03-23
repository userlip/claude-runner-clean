<div>
    <x-header title="{{ $report->title }}" separator>
        <x-slot:actions>
            <x-button label="Back" icon="o-arrow-left" link="{{ route('workbench.research-reports.index') }}" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card shadow class="lg:col-span-1">
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-base-content/60">Module</dt>
                    <dd class="font-semibold">{{ $report->module->label() }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-base-content/60">Findings</dt>
                    <dd class="font-semibold">{{ $report->findings_count }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-base-content/60">Proposals Created</dt>
                    <dd class="font-semibold">{{ $report->proposals_created }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-base-content/60">Date</dt>
                    <dd class="font-semibold">{{ $report->created_at->format('Y-m-d H:i') }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card shadow class="lg:col-span-2">
            <h2 class="mb-2 text-lg font-semibold">Summary</h2>
            <p class="text-base-content/70">{{ $report->summary }}</p>

            @if($report->content)
                <hr class="my-4" />
                <h2 class="mb-2 text-lg font-semibold">Full Report</h2>
                <div class="prose max-w-none">
                    {!! nl2br(e($report->content)) !!}
                </div>
            @endif
        </x-card>
    </div>
</div>
