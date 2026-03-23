<?php

namespace App\Livewire;

use App\Models\Repository;
use App\Models\Task;
use App\Services\AsanaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class AsanaBoard extends Component
{
    use Toast;

    public ?string $selectedWorkspaceId = null;

    public ?string $selectedProjectId = null;

    public ?string $linkedRepositoryId = null;

    public ?string $testingSectionId = null;

    public ?string $selectedTaskId = null;

    public bool $showTaskPanel = false;

    /** @var array<int, array{gid: string, name: string}> */
    public array $workspaces = [];

    /** @var array<int, array{gid: string, name: string}> */
    public array $projects = [];

    /** @var array<string, array{gid: string, name: string, tasks: array<int, array<string, mixed>>}> */
    public array $sections = [];

    /** @var array<string, mixed>|null */
    public ?array $selectedTask = null;

    /** @var array<int, array{id: int, name: string}> */
    public array $repositories = [];

    public function mount(): void
    {
        $this->loadWorkspaces();
        $this->loadRepositories();
    }

    public function loadWorkspaces(): void
    {
        $connection = Auth::user()?->asanaConnection()->first();

        if (! $connection) {
            $this->workspaces = [];

            return;
        }

        $cacheKey = "asana.workspaces.{$connection->id}";

        $this->workspaces = Cache::remember($cacheKey, 60, function () use ($connection) {
            $service = app(AsanaService::class, ['personalAccessToken' => $connection->credentials]);
            $response = $service->getWorkspaces();

            return $response['data'] ?? [];
        });

        // Pre-select default workspace if available
        if (empty($this->selectedWorkspaceId) && ! empty($this->workspaces)) {
            $defaultWorkspaceId = $connection->metadata['default_workspace_id'] ?? null;
            $this->selectedWorkspaceId = $defaultWorkspaceId ?? $this->workspaces[0]['gid'];
            $this->loadProjects();
        }
    }

    public function loadProjects(): void
    {
        if (! $this->selectedWorkspaceId) {
            $this->projects = [];

            return;
        }

        $connection = Auth::user()?->asanaConnection()->first();

        if (! $connection) {
            return;
        }

        $cacheKey = "asana.projects.{$connection->id}.{$this->selectedWorkspaceId}";

        $this->projects = Cache::remember($cacheKey, 60, function () use ($connection) {
            $service = app(AsanaService::class, ['personalAccessToken' => $connection->credentials]);
            $response = $service->getWorkspaceProjects($this->selectedWorkspaceId);

            return $response['data'] ?? [];
        });
    }

    public function loadRepositories(): void
    {
        $this->repositories = Repository::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function updatedSelectedWorkspaceId(): void
    {
        $this->selectedProjectId = null;
        $this->linkedRepositoryId = null;
        $this->testingSectionId = null;
        $this->sections = [];
        $this->loadProjects();
    }

    public function updatedSelectedProjectId(): void
    {
        if (! $this->selectedProjectId) {
            $this->linkedRepositoryId = null;
            $this->testingSectionId = null;
            $this->sections = [];

            return;
        }

        // Check if there's a linked repository
        $linkedRepo = Repository::where('asana_project_id', $this->selectedProjectId)
            ->where('user_id', Auth::id())
            ->first();

        if ($linkedRepo) {
            $this->linkedRepositoryId = (string) $linkedRepo->id;
            $this->testingSectionId = $linkedRepo->asana_testing_section_id;
        } else {
            $this->linkedRepositoryId = null;
            $this->testingSectionId = null;
        }

        $this->loadSections();
    }

    public function updatedLinkedRepositoryId(): void
    {
        if (! $this->selectedProjectId) {
            return;
        }

        if ($this->linkedRepositoryId) {
            // Link repository to this project
            Repository::where('id', $this->linkedRepositoryId)
                ->where('user_id', Auth::id())
                ->update(['asana_project_id' => $this->selectedProjectId]);

            $this->success('Repository linked to project');
        } else {
            // Unlink any repository from this project
            Repository::where('asana_project_id', $this->selectedProjectId)
                ->where('user_id', Auth::id())
                ->update([
                    'asana_project_id' => null,
                    'asana_testing_section_id' => null,
                ]);

            $this->testingSectionId = null;
            $this->success('Repository unlinked');
        }
    }

    public function updatedTestingSectionId(): void
    {
        if (! $this->linkedRepositoryId || ! $this->selectedProjectId) {
            return;
        }

        Repository::where('id', $this->linkedRepositoryId)
            ->where('user_id', Auth::id())
            ->update(['asana_testing_section_id' => $this->testingSectionId]);

        $this->success('Testing section updated');
    }

    public function loadSections(): void
    {
        if (! $this->selectedProjectId) {
            $this->sections = [];

            return;
        }

        $connection = Auth::user()?->asanaConnection()->first();

        if (! $connection) {
            return;
        }

        $cacheKey = "asana.sections.{$connection->id}.{$this->selectedProjectId}";

        $this->sections = Cache::remember($cacheKey, 60, function () use ($connection) {
            $service = app(AsanaService::class, ['personalAccessToken' => $connection->credentials]);

            // Get sections
            $sectionsResponse = $service->getProjectSections($this->selectedProjectId);
            $sections = $sectionsResponse['data'] ?? [];

            // Get tasks
            $tasksResponse = $service->getProjectTasks($this->selectedProjectId);
            $tasks = $tasksResponse['data'] ?? [];

            // Group tasks by section
            $sectionMap = [];

            foreach ($sections as $section) {
                $sectionMap[$section['gid']] = [
                    'gid' => $section['gid'],
                    'name' => $section['name'],
                    'tasks' => [],
                ];
            }

            foreach ($tasks as $task) {
                $sectionId = $task['section']['gid'] ?? null;

                if ($sectionId && isset($sectionMap[$sectionId])) {
                    $sectionMap[$sectionId]['tasks'][] = $task;
                }
            }

            return $sectionMap;
        });
    }

    public function openTaskPanel(string $taskId): void
    {
        $this->selectedTaskId = $taskId;
        $this->showTaskPanel = true;

        $connection = Auth::user()?->asanaConnection()->first();

        if (! $connection) {
            return;
        }

        $service = app(AsanaService::class, ['personalAccessToken' => $connection->credentials]);
        $taskDetails = $service->getTaskDetails($taskId);

        $this->selectedTask = $taskDetails['data'] ?? null;
    }

    public function closeTaskPanel(): void
    {
        $this->showTaskPanel = false;
        $this->selectedTaskId = null;
        $this->selectedTask = null;
    }

    public function startClaudeRunnerTask(): void
    {
        if (! $this->selectedTask || ! $this->linkedRepositoryId) {
            $this->error('No repository linked to this project');

            return;
        }

        $taskTitle = $this->selectedTask['name'] ?? 'Asana Task';
        $taskDescription = $this->selectedTask['notes'] ?? '';
        $asanaTaskId = $this->selectedTask['gid'] ?? null;

        // Check if a CR task already exists for this Asana task
        $existingTask = Task::where('asana_task_id', $asanaTaskId)
            ->where('user_id', Auth::id())
            ->first();

        if ($existingTask) {
            $this->redirectRoute('workbench.tasks.show', ['uuid' => $existingTask->uuid]);

            return;
        }

        // Create new CR task
        $task = Task::create([
            'user_id' => Auth::id(),
            'title' => $taskTitle,
            'repository_id' => $this->linkedRepositoryId,
            'asana_task_id' => $asanaTaskId,
            'status' => \App\Enums\TaskStatus::Pending,
        ]);

        // If there's a description, create an initial message
        if (! empty($taskDescription)) {
            $task->messages()->create([
                'role' => \App\Enums\MessageRole::User,
                'content' => $taskDescription,
            ]);
        }

        $this->closeTaskPanel();
        $this->success('Claude Runner task created');
        $this->redirectRoute('workbench.tasks.show', ['uuid' => $task->uuid]);
    }

    public function unlinkRepository(): void
    {
        if (! $this->linkedRepositoryId) {
            return;
        }

        Repository::where('id', $this->linkedRepositoryId)
            ->where('user_id', Auth::id())
            ->update([
                'asana_project_id' => null,
                'asana_testing_section_id' => null,
            ]);

        $this->linkedRepositoryId = null;
        $this->testingSectionId = null;
        $this->success('Repository unlinked from project');
    }

    public function hasAsanaConnection(): bool
    {
        return Auth::user()?->asanaConnection()->exists() ?? false;
    }

    public function render(): View
    {
        return view('livewire.asana-board');
    }
}
