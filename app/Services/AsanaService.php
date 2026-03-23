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
     * Find a testing section in a project by name pattern.
     * Looks for sections with names containing 'test', 'testing', or 'qa'.
     *
     * @param  string  $projectId  The Asana project GID
     * @return string|null The section GID or null if not found
     */
    public function findTestingSection(string $projectId): ?string
    {
        $sections = $this->getProjectSections($projectId);

        foreach ($sections['data'] ?? [] as $section) {
            $name = strtolower($section['name'] ?? '');
            if (str_contains($name, 'test') || str_contains($name, 'testing') || str_contains($name, 'qa')) {
                return $section['gid'];
            }
        }

        return null;
    }
}
