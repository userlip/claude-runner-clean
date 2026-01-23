<?php

namespace App\Services;

use App\Models\GitHubConnection;
use App\Models\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GitHubService
{
    private const API_BASE = 'https://api.github.com';

    public function __construct(
        private GitHubConnection $connection
    ) {}

    public function fetchRepositories(): Collection
    {
        $repos = collect();
        $page = 1;
        $perPage = 100;

        do {
            $response = Http::withToken($this->connection->access_token)
                ->accept('application/vnd.github+json')
                ->get(self::API_BASE.'/user/repos', [
                    'per_page' => $perPage,
                    'page' => $page,
                    'sort' => 'updated',
                    'affiliation' => 'owner,collaborator,organization_member',
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to fetch repositories: '.$response->body());
            }

            $pageRepos = collect($response->json());
            $repos = $repos->concat($pageRepos);
            $page++;
        } while ($pageRepos->count() === $perPage);

        return $repos;
    }

    public function syncRepositories(): int
    {
        $repos = $this->fetchRepositories();
        $synced = 0;

        foreach ($repos as $repo) {
            Repository::updateOrCreate(
                [
                    'user_id' => $this->connection->user_id,
                    'github_id' => $repo['id'],
                ],
                [
                    'name' => $repo['name'],
                    'full_name' => $repo['full_name'],
                    'clone_url' => $repo['clone_url'],
                    'ssh_url' => $repo['ssh_url'],
                    'default_branch' => $repo['default_branch'],
                    'private' => $repo['private'],
                    'description' => $repo['description'],
                ]
            );
            $synced++;
        }

        return $synced;
    }

    public function fetchDependabotPullRequests(string $fullName): Collection
    {
        $response = Http::withToken($this->connection->access_token)
            ->accept('application/vnd.github+json')
            ->get(self::API_BASE."/repos/{$fullName}/pulls", [
                'state' => 'open',
                'per_page' => 100,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch PRs: '.$response->body());
        }

        return collect($response->json())
            ->filter(fn ($pr) => ($pr['user']['login'] ?? '') === 'dependabot[bot]')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchCombinedStatus(string $fullName, string $sha): array
    {
        $response = Http::withToken($this->connection->access_token)
            ->accept('application/vnd.github+json')
            ->get(self::API_BASE."/repos/{$fullName}/commits/{$sha}/status");

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch status: '.$response->body());
        }

        $status = $response->json();

        if (($status['state'] ?? null) === 'pending') {
            $checkRuns = $this->fetchCheckRunsStatus($fullName, $sha);
            if ($checkRuns) {
                return $checkRuns;
            }
        }

        return $status;
    }

    /**
     * @return array<string, mixed> | null
     */
    private function fetchCheckRunsStatus(string $fullName, string $sha): ?array
    {
        $response = Http::withToken($this->connection->access_token)
            ->accept('application/vnd.github+json')
            ->get(self::API_BASE."/repos/{$fullName}/commits/{$sha}/check-runs");

        if ($response->failed()) {
            return null;
        }

        $payload = $response->json();
        $runs = collect($payload['check_runs'] ?? []);

        if ($runs->isEmpty()) {
            return null;
        }

        $conclusions = $runs->pluck('conclusion')->filter()->unique();
        $statuses = $runs->pluck('status')->filter()->unique();

        $state = 'pending';
        if ($conclusions->contains('failure') || $conclusions->contains('cancelled') || $conclusions->contains('timed_out') || $conclusions->contains('action_required') || $conclusions->contains('stale')) {
            $state = 'failure';
        } elseif ($conclusions->contains('success') && $statuses->every(fn ($status) => $status === 'completed')) {
            $state = 'success';
        } elseif ($statuses->contains('in_progress') || $statuses->contains('queued')) {
            $state = 'pending';
        }

        return [
            'state' => $state,
            'source' => 'check_runs',
            'total_count' => $payload['total_count'] ?? $runs->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mergePullRequest(string $fullName, int $number): array
    {
        $response = Http::withToken($this->connection->access_token)
            ->accept('application/vnd.github+json')
            ->put(self::API_BASE."/repos/{$fullName}/pulls/{$number}/merge", [
                'merge_method' => 'squash',
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to merge PR: '.$response->body());
        }

        return $response->json();
    }
}
