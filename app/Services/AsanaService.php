<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AsanaService
{
    protected string $baseUrl = 'https://app.asana.com/api/1.0';

    public function __construct(public string $personalAccessToken) {}

    /**
     * Fetch all workspaces for the authenticated user.
     *
     * @return array{data: array<int, array{gid: string, name: string}>}
     */
    public function getWorkspaces(): array
    {
        $response = Http::withToken($this->personalAccessToken)
            ->get("{$this->baseUrl}/workspaces");

        if (! $response->successful()) {
            return ['data' => []];
        }

        return $response->json();
    }

    /**
     * Validate the PAT by attempting to fetch the current user.
     */
    public function validateToken(): bool
    {
        $response = Http::withToken($this->personalAccessToken)
            ->get("{$this->baseUrl}/users/me");

        return $response->successful();
    }

    /**
     * Add a comment to an Asana task.
     *
     * @param  string  $taskId  The Asana task GID
     * @param  string  $text  The comment text
     * @return array{data: array{gid: string}}|null
     */
    public function addCommentToTask(string $taskId, string $text): ?array
    {
        $response = Http::withToken($this->personalAccessToken)
            ->post("{$this->baseUrl}/tasks/{$taskId}/stories", [
                'data' => [
                    'text' => $text,
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Move a task to a different section.
     *
     * @param  string  $taskId  The Asana task GID
     * @param  string  $sectionId  The section GID
     * @return array{data: array{gid: string}}|null
     */
    public function moveTaskToSection(string $taskId, string $sectionId): ?array
    {
        $response = Http::withToken($this->personalAccessToken)
            ->post("{$this->baseUrl}/sections/{$sectionId}/addTask", [
                'data' => [
                    'task' => $taskId,
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Update a task's properties including section.
     *
     * @param  string  $taskId  The Asana task GID
     * @param  array<string, mixed>  $data  Task data to update
     * @return array{data: array{gid: string}}|null
     */
    public function updateTask(string $taskId, array $data): ?array
    {
        $response = Http::withToken($this->personalAccessToken)
            ->put("{$this->baseUrl}/tasks/{$taskId}", [
                'data' => $data,
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get all sections for a project.
     *
     * @param  string  $projectId  The Asana project GID
     * @return array{data: array<int, array{gid: string, name: string}>}
     */
    public function getProjectSections(string $projectId): array
    {
        $response = Http::withToken($this->personalAccessToken)
            ->get("{$this->baseUrl}/projects/{$projectId}/sections");

        if (! $response->successful()) {
            return ['data' => []];
        }

        return $response->json();
    }

    /**
     * Get all projects for a workspace.
     *
     * @param  string  $workspaceId  The Asana workspace GID
     * @return array{data: array<int, array{gid: string, name: string, archived?: bool}>}
     */
    public function getWorkspaceProjects(string $workspaceId): array
    {
        $response = Http::withToken($this->personalAccessToken)
            ->get("{$this->baseUrl}/projects", [
                'workspace' => $workspaceId,
                'archived' => false,
            ]);

        if (! $response->successful()) {
            return ['data' => []];
        }

        return $response->json();
    }

    /**
     * Get all tasks for a project.
     *
     * @param  string  $projectId  The Asana project GID
     * @return array{data: array<int, array{gid: string, name: string, completed: bool, assignee?: array{gid: string, name: string}, due_on?: string, section?: array{gid: string, name: string}}>}
     */
    public function getProjectTasks(string $projectId): array
    {
        $response = Http::withToken($this->personalAccessToken)
            ->get("{$this->baseUrl}/tasks", [
                'project' => $projectId,
                'opt_fields' => 'name,completed,assignee.name,due_on,section.name,section.gid,created_at,modified_at,tags',
            ]);

        if (! $response->successful()) {
            return ['data' => []];
        }

        return $response->json();
    }

    /**
     * Get full task details including stories/comments.
     *
     * @param  string  $taskId  The Asana task GID
     * @return array{data: array{gid: string, name: string, notes?: string, completed: bool, assignee?: array{gid: string, name: string}, due_on?: string, section?: array{gid: string, name: string}}}|null
     */
    public function getTaskDetails(string $taskId): ?array
    {
        $response = Http::withToken($this->personalAccessToken)
            ->get("{$this->baseUrl}/tasks/{$taskId}", [
                'opt_fields' => 'name,notes,completed,assignee.name,due_on,section.name,section.gid,created_at,modified_at,tags',
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Find a testing section in a project by name pattern.
     * Matches sections named like "test", "testing", "qa", etc.
     *
     * @param  string  $projectId  The Asana project GID
     * @return string|null The section GID or null if not found
     */
    public function findTestingSection(string $projectId): ?string
    {
        $sections = $this->getProjectSections($projectId);
        $data = $sections['data'] ?? [];

        foreach ($data as $section) {
            $name = strtolower($section['name'] ?? '');

            // Match sections with test/qa related names
            if (preg_match('/\b(test|testing|qa)\b/i', $name)) {
                return $section['gid'];
            }
        }

        return null;
    }

    /**
     * Create a new task in Asana.
     *
     * @param  string  $projectId  The Asana project GID
     * @param  string  $sectionId  The section GID to add the task to
     * @param  array<string, mixed>  $data  Task data (name, notes, assignee, due_on, etc.)
     * @return array{data: array{gid: string, name: string}}|null
     */
    public function createTask(string $projectId, string $sectionId, array $data): ?array
    {
        $payload = [
            'data' => [
                'name' => $data['name'],
                'projects' => [$projectId],
                'memberships' => [
                    [
                        'project' => $projectId,
                        'section' => $sectionId,
                    ],
                ],
            ],
        ];

        // Optional fields
        if (! empty($data['notes'])) {
            $payload['data']['notes'] = $data['notes'];
        }

        if (! empty($data['assignee'])) {
            $payload['data']['assignee'] = $data['assignee'];
        }

        if (! empty($data['due_on'])) {
            $payload['data']['due_on'] = $data['due_on'];
        }

        $response = Http::withToken($this->personalAccessToken)
            ->post("{$this->baseUrl}/tasks", $payload);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get all users in a workspace for assignee selection.
     *
     * @param  string  $workspaceId  The Asana workspace GID
     * @return array<int, array{gid: string, name: string, email?: string}>
     */
    public function getWorkspaceUsers(string $workspaceId): array
    {
        $response = Http::withToken($this->personalAccessToken)
            ->get("{$this->baseUrl}/workspaces/{$workspaceId}/users", [
                'opt_fields' => 'name,email',
            ]);

        if (! $response->successful()) {
            return [];
        }

        return $response->json()['data'] ?? [];
    }

    /**
     * Delete a task in Asana.
     *
     * @param  string  $taskId  The Asana task GID
     */
    public function deleteTask(string $taskId): bool
    {
        $response = Http::withToken($this->personalAccessToken)
            ->delete("{$this->baseUrl}/tasks/{$taskId}");

        return $response->successful();
    }
}
