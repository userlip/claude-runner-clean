<?php

namespace App\Filament\Pages;

use App\Enums\ConnectionType;
use App\Models\Connection;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Connections extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Connections';

    protected static ?string $title = 'All Connections';

    protected static ?string $slug = 'connections';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.connections';

    /**
     * Get all connection types with their status and details.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getConnections(): Collection
    {
        $connections = collect();

        foreach (ConnectionType::cases() as $type) {
            $connection = Auth::user()->connectionOfType($type);
            $connections->push([
                'type' => $type,
                'connection' => $connection,
                'is_connected' => $connection !== null,
                'details' => $this->getConnectionDetails($type, $connection),
            ]);
        }

        return $connections;
    }

    /**
     * Get connection-specific details for display.
     *
     * @return array<string, mixed>
     */
    private function getConnectionDetails(ConnectionType $type, ?Connection $connection): array
    {
        if ($connection === null) {
            return [
                'description' => $this->getDefaultDescription($type),
                'status_text' => 'Not Connected',
            ];
        }

        return match ($type) {
            ConnectionType::GitHub => [
                'title' => $connection->github_username ?? 'GitHub Account',
                'description' => 'Connected with repository access',
                'status_text' => 'Connected',
            ],
            ConnectionType::GoogleAnalytics => [
                'title' => $connection->name,
                'description' => $connection->getClientEmail() ?? 'Service account connected',
                'status_text' => 'Connected',
                'property_id' => $connection->property_id,
            ],
            ConnectionType::SearchConsole => [
                'title' => $connection->name,
                'description' => $connection->getClientEmail() ?? 'Service account connected',
                'status_text' => 'Connected',
            ],
            ConnectionType::Asana => [
                'title' => $connection->name,
                'description' => 'Personal Access Token configured',
                'status_text' => 'Connected',
            ],
        };
    }

    /**
     * Get the default description for a connection type when not connected.
     */
    private function getDefaultDescription(ConnectionType $type): string
    {
        return match ($type) {
            ConnectionType::GitHub => 'Sync and manage your GitHub repositories',
            ConnectionType::GoogleAnalytics => 'View analytics reports and property data',
            ConnectionType::SearchConsole => 'Access search analytics and sitemap data',
            ConnectionType::Asana => 'Manage projects and tasks from your workspace',
        };
    }

    /**
     * Get the settings route for a connection type.
     */
    public function getSettingsRoute(ConnectionType $type): string
    {
        return match ($type) {
            ConnectionType::GitHub => route('filament.admin.pages.git-hub-settings'),
            ConnectionType::GoogleAnalytics => route('filament.admin.pages.google-analytics-settings'),
            ConnectionType::SearchConsole => route('filament.admin.pages.search-console-settings'),
            ConnectionType::Asana => route('filament.admin.pages.asana-settings'),
        };
    }

    /**
     * Get the total number of connected integrations.
     */
    public function getConnectedCount(): int
    {
        return Auth::user()->connections()->where('is_active', true)->count();
    }

    /**
     * Get the total number of available integration types.
     */
    public function getTotalCount(): int
    {
        return count(ConnectionType::cases());
    }
}
