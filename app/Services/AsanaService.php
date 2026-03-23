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
}
