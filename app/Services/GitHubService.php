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
}
