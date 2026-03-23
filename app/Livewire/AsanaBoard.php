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

    /** @var array<int, array{gid: string, name: string}> */
    public array $workspaceUsers = [];

    // Inline quick-add properties
    public ?string $inlineSectionId = null;

    public string $inlineTitle = '';

    // Full form modal properties
    public bool $showCreateTaskModal = false;

    public string $newTaskTitle = '';

    public string $newTaskDescription = '';

    public ?string $newTaskAssignee = null;

    public ?string $newTaskDueDate = null;

    public ?string $newTaskSectionId = null;

    // Drag and drop properties
    public ?string $draggedTaskId = null;

    public ?string $draggedTaskSourceSection = null;

    public ?string $dropTargetSection = null;

    // Task edit properties
    public bool $isEditingTask = false;

    public string $editTaskTitle = '';

    public string $editTaskDescription = '';

    public ?string $editTaskAssignee = null;

    public ?string $editTaskDueDate = null;

    public ?string $editTaskSectionId = null;

    public bool $showDeleteConfirm = false;

    /** @var array<string, string> */
    protected array $validationErrors = [];

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

    /**
     * Show inline quick-add form for a section.
     */
    public function showInlineAdd(string $sectionId): void
    {
        $this->inlineSectionId = $sectionId;
        $this->inlineTitle = '';
    }

    /**
     * Hide inline quick-add form.
     */
    public function hideInlineAdd(): void
    {
        $this->inlineSectionId = null;
        $this->inlineTitle = '';
    }

    /**
     * Create a task using inline quick-add.
     */
    public function createInlineTask(): void
    {
        if (empty($this->inlineTitle)) {
            $this->error('Task title is required');

            return;
        }

        if (! $this->selectedProjectId || ! $this->inlineSectionId) {
            $this->error('No project or section selected');

            return;
        }

        $connection = Auth::user()?->asanaConnection()->first();

        if (! $connection) {
            $this->error('No Asana connection found');

            return;
        }

        $service = app(AsanaService::class, ['personalAccessToken' => $connection->credentials]);

        $result = $service->createTask($this->selectedProjectId, $this->inlineSectionId, [
            'name' => $this->inlineTitle,
        ]);

        if ($result === null) {
            $this->error('Failed to create task');

            return;
        }

        // Clear cache to refresh board
        $this->clearAsanaCache();

        $this->hideInlineAdd();
        $this->success('Task created successfully');

        // Reload sections to show new task
        $this->loadSections();
    }

    /**
     * Open the full create task modal.
     */
    public function openCreateTaskModal(?string $sectionId = null): void
    {
        $this->resetValidation();
        $this->newTaskTitle = '';
        $this->newTaskDescription = '';
        $this->newTaskAssignee = null;
        $this->newTaskDueDate = null;
        $this->newTaskSectionId = $sectionId ?? (array_key_first($this->sections) ?: null);
        $this->showCreateTaskModal = true;

        // Load workspace users for assignee dropdown
        $this->loadWorkspaceUsers();
    }

    /**
     * Close the create task modal.
     */
    public function closeCreateTaskModal(): void
    {
        $this->showCreateTaskModal = false;
        $this->newTaskTitle = '';
        $this->newTaskDescription = '';
        $this->newTaskAssignee = null;
        $this->newTaskDueDate = null;
        $this->newTaskSectionId = null;
    }

    /**
     * Create a task using the full form.
     */
    public function createFullTask(): void
    {
        $this->validate([
            'newTaskTitle' => 'required|string|max:255',
            'newTaskDueDate' => 'nullable|date_format:Y-m-d',
        ]);

        if (! $this->selectedProjectId || ! $this->newTaskSectionId) {
            $this->error('No project or section selected');

            return;
        }

        $connection = Auth::user()?->asanaConnection()->first();

        if (! $connection) {
            $this->error('No Asana connection found');

            return;
        }

        $service = app(AsanaService::class, ['personalAccessToken' => $connection->credentials]);

        $taskData = [
            'name' => $this->newTaskTitle,
            'notes' => $this->newTaskDescription,
        ];

        if ($this->newTaskAssignee) {
            $taskData['assignee'] = $this->newTaskAssignee;
        }

        if ($this->newTaskDueDate) {
            $taskData['due_on'] = $this->newTaskDueDate;
        }

        $result = $service->createTask($this->selectedProjectId, $this->newTaskSectionId, $taskData);

        if ($result === null) {
            $this->error('Failed to create task');

            return;
        }

        // Clear cache to refresh board
        $this->clearAsanaCache();

        $this->closeCreateTaskModal();
        $this->success('Task created successfully');

        // Reload sections to show new task
        $this->loadSections();
    }

    /**
     * Load workspace users for assignee selection.
     */
    public function loadWorkspaceUsers(): void
    {
        if (! $this->selectedWorkspaceId) {
            $this->workspaceUsers = [];

            return;
        }

        $connection = Auth::user()?->asanaConnection()->first();

        if (! $connection) {
            $this->workspaceUsers = [];

            return;
        }

        $service = app(AsanaService::class, ['personalAccessToken' => $connection->credentials]);
        $users = $service->getWorkspaceUsers($this->selectedWorkspaceId);

        $this->workspaceUsers = $users;
    }

    /**
     * Clear Asana cache to refresh data.
     */
    protected function clearAsanaCache(): void
    {
        $connection = Auth::user()?->asanaConnection()->first();

        if (! $connection) {
            return;
        }

        // Clear sections cache for current project
        if ($this->selectedProjectId) {
            Cache::forget("asana.sections.{$connection->id}.{$this->selectedProjectId}");
        }

        // Clear other caches as needed
        if ($this->selectedWorkspaceId) {
            Cache::forget("asana.projects.{$connection->id}.{$this->selectedWorkspaceId}");
        }
    }

    /**
     * Start dragging a task.
     */
    public function startDrag(string $taskId, string $sourceSectionId): void
    {
        $this->draggedTaskId = $taskId;
        $this->draggedTaskSourceSection = $sourceSectionId;
    }

    /**
     * Set drop target section for visual feedback.
     */
    public function setDropTarget(?string $sectionId): void
    {
        $this->dropTargetSection = $sectionId;
    }

    /**
     * Move a task to a different section (drag-and-drop).
     */
    public function moveTaskToSection(string $taskId, string $targetSectionId): void
    {
        // Validate we have required data
        if (! $this->selectedProjectId) {
            $this->error('No project selected');

            return;
        }

        // Prevent dropping in same section
        if ($this->draggedTaskSourceSection === $targetSectionId) {
            $this->clearDragState();

            return;
        }

        $connection = Auth::user()?->asanaConnection()->first();

        if (! $connection) {
            $this->error('No Asana connection found');
            $this->clearDragState();

            return;
        }

        // Optimistic UI: Move task in local state immediately
        $task = null;
        $sourceSection = $this->sections[$this->draggedTaskSourceSection] ?? null;

        if ($sourceSection) {
            // Find and remove task from source section
            foreach ($sourceSection['tasks'] as $index => $t) {
                if ($t['gid'] === $taskId) {
                    $task = $t;
                    unset($this->sections[$this->draggedTaskSourceSection]['tasks'][$index]);
                    $this->sections[$this->draggedTaskSourceSection]['tasks'] = array_values($this->sections[$this->draggedTaskSourceSection]['tasks']);
                    break;
                }
            }
        }

        // Add task to target section with updated section reference
        if ($task && isset($this->sections[$targetSectionId])) {
            $task['section'] = [
                'gid' => $targetSectionId,
                'name' => $this->sections[$targetSectionId]['name'],
            ];
            $this->sections[$targetSectionId]['tasks'][] = $task;
        }

        // Clear drag state
        $this->clearDragState();

        // Call Asana API to update task section
        $service = app(AsanaService::class, ['personalAccessToken' => $connection->credentials]);
        $result = $service->moveTaskToSection($taskId, $targetSectionId);

        if ($result === null) {
            // API failed - revert the optimistic update
            $this->error('Failed to move task. Reverting...');
            $this->loadSections();

            return;
        }

        // Clear cache and reload to ensure consistency
        $this->clearAsanaCache();
        $this->success('Task moved successfully');
    }

    /**
     * Clear drag state.
     */
    protected function clearDragState(): void
    {
        $this->draggedTaskId = null;
        $this->draggedTaskSourceSection = null;
        $this->dropTargetSection = null;
    }

    /**
     * Enter edit mode for the selected task.
     */
    public function startEditMode(): void
    {
        if (! $this->selectedTask) {
            return;
        }

        $this->editTaskTitle = $this->selectedTask['name'] ?? '';
        $this->editTaskDescription = $this->selectedTask['notes'] ?? '';
        $this->editTaskAssignee = $this->selectedTask['assignee']['gid'] ?? null;
        $this->editTaskDueDate = $this->selectedTask['due_on'] ?? null;
        $this->editTaskSectionId = $this->selectedTask['section']['gid'] ?? null;
        $this->isEditingTask = true;

        // Load workspace users for assignee dropdown
        $this->loadWorkspaceUsers();
    }

    /**
     * Cancel edit mode and return to view mode.
     */
    public function cancelEdit(): void
    {
        $this->isEditingTask = false;
        $this->editTaskTitle = '';
        $this->editTaskDescription = '';
        $this->editTaskAssignee = null;
        $this->editTaskDueDate = null;
        $this->editTaskSectionId = null;
    }

    /**
     * Save task changes to Asana.
     */
    public function saveTaskChanges(): void
    {
        $this->validate([
            'editTaskTitle' => 'required|string|max:255',
            'editTaskDueDate' => 'nullable|date_format:Y-m-d',
        ]);

        if (! $this->selectedTaskId || ! $this->selectedTask) {
            $this->error('No task selected');

            return;
        }

        $connection = Auth::user()?->asanaConnection()->first();

        if (! $connection) {
            $this->error('No Asana connection found');

            return;
        }

        $service = app(AsanaService::class, ['personalAccessToken' => $connection->credentials]);

        $updateData = [
            'name' => $this->editTaskTitle,
            'notes' => $this->editTaskDescription,
        ];

        if ($this->editTaskAssignee) {
            $updateData['assignee'] = $this->editTaskAssignee;
        } else {
            $updateData['assignee'] = null;
        }

        if ($this->editTaskDueDate) {
            $updateData['due_on'] = $this->editTaskDueDate;
        } else {
            $updateData['due_on'] = null;
        }

        // Handle section change if different
        $currentSectionId = $this->selectedTask['section']['gid'] ?? null;
        if ($this->editTaskSectionId && $this->editTaskSectionId !== $currentSectionId) {
            $service->moveTaskToSection($this->selectedTaskId, $this->editTaskSectionId);
        }

        $result = $service->updateTask($this->selectedTaskId, $updateData);

        if ($result === null) {
            $this->error('Failed to update task');

            return;
        }

        // Clear cache to refresh board
        $this->clearAsanaCache();

        // Refresh task details
        $taskDetails = $service->getTaskDetails($this->selectedTaskId);
        $this->selectedTask = $taskDetails['data'] ?? null;

        $this->isEditingTask = false;
        $this->success('Task updated successfully');
        $this->loadSections();
    }

    /**
     * Show delete confirmation dialog.
     */
    public function confirmDelete(): void
    {
        $this->showDeleteConfirm = true;
    }

    /**
     * Cancel delete and hide confirmation.
     */
    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
    }

    /**
     * Delete the selected task from Asana.
     */
    public function deleteTask(): void
    {
        if (! $this->selectedTaskId) {
            $this->error('No task selected');

            return;
        }

        $connection = Auth::user()?->asanaConnection()->first();

        if (! $connection) {
            $this->error('No Asana connection found');

            return;
        }

        $service = app(AsanaService::class, ['personalAccessToken' => $connection->credentials]);

        $success = $service->deleteTask($this->selectedTaskId);

        if (! $success) {
            $this->error('Failed to delete task');

            return;
        }

        // Clear cache to refresh board
        $this->clearAsanaCache();

        $this->showDeleteConfirm = false;
        $this->closeTaskPanel();
        $this->success('Task deleted successfully');
        $this->loadSections();
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
