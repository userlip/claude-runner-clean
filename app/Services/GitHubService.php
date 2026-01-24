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
    public function fetchPullRequest(string $fullName, int $number): array
    {
        $response = Http::withToken($this->connection->access_token)
            ->accept('application/vnd.github+json')
            ->get(self::API_BASE."/repos/{$fullName}/pulls/{$number}");

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch PR: '.$response->body());
        }

        return $response->json();
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
        $filteredStatuses = collect($status['statuses'] ?? [])
            ->reject(fn (array $item) => $this->isIgnoredStatusContext($item['context'] ?? ''));

        $checkRuns = $this->fetchCheckRunsSummary($fullName, $sha);

        $status['total_count'] = $filteredStatuses->count();
        $status['check_runs_total_count'] = $checkRuns['total_count'] ?? 0;
        $status['check_runs_ignored_count'] = $checkRuns['ignored_total_count'] ?? 0;

        $states = [];
        if ($filteredStatuses->isNotEmpty()) {
            $states[] = $this->calculateLegacyStatusState($filteredStatuses);
        }

        if (($checkRuns['total_count'] ?? 0) > 0 && isset($checkRuns['state'])) {
            $states[] = $checkRuns['state'];
            $status['source'] = $checkRuns['source'] ?? 'check_runs';
        }

        if (empty($states)) {
            $status['state'] = 'pending';
        } elseif (in_array('failure', $states, true)) {
            $status['state'] = 'failure';
        } elseif (in_array('pending', $states, true)) {
            $status['state'] = 'pending';
        } else {
            $status['state'] = 'success';
        }

        return $status;
    }

    /**
     * @return array<string, mixed> | null
     */
    private function fetchCheckRunsSummary(string $fullName, string $sha): ?array
    {
        $response = Http::withToken($this->connection->access_token)
            ->accept('application/vnd.github+json')
            ->get(self::API_BASE."/repos/{$fullName}/commits/{$sha}/check-runs");

        if ($response->failed()) {
            return null;
        }

        $payload = $response->json();
        $runs = collect($payload['check_runs'] ?? []);
        $filteredRuns = $runs->reject(fn (array $run) => $this->isIgnoredCheckRun($run))->values();
        $ignoredCount = $runs->count() - $filteredRuns->count();

        if ($filteredRuns->isEmpty()) {
            return [
                'state' => 'pending',
                'source' => 'check_runs',
                'total_count' => 0,
                'ignored_total_count' => $ignoredCount,
            ];
        }

        $conclusions = $filteredRuns->pluck('conclusion')->filter()->unique();
        $statuses = $filteredRuns->pluck('status')->filter()->unique();

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
            'total_count' => $filteredRuns->count(),
            'ignored_total_count' => $ignoredCount,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $statuses
     */
    private function calculateLegacyStatusState(\Illuminate\Support\Collection $statuses): string
    {
        $states = $statuses->pluck('state')->filter()->unique();

        if ($states->contains('failure')) {
            return 'failure';
        }

        if ($states->contains('pending')) {
            return 'pending';
        }

        return $states->contains('success') ? 'success' : 'pending';
    }

    private function isIgnoredStatusContext(string $context): bool
    {
        $context = strtolower($context);

        return str_contains($context, 'claude')
            || str_contains($context, 'code review');
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function isIgnoredCheckRun(array $run): bool
    {
        $name = strtolower((string) ($run['name'] ?? $run['app']['name'] ?? $run['external_id'] ?? ''));

        return str_contains($name, 'claude')
            || str_contains($name, 'code review');
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
