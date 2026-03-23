<?php

namespace App\Filament\Pages;

use App\Enums\ConnectionType;
use App\Models\Connection;
use App\Services\AsanaService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AsanaSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Asana';

    protected static ?string $title = 'Asana Connection';

    protected static ?string $slug = 'asana-settings';

    protected string $view = 'filament.pages.asana-settings';

    public string $personalAccessToken = '';

    public string $defaultWorkspaceId = '';

    /** @var array<int, array{gid: string, name: string}> */
    public array $workspaces = [];

    public function mount(): void
    {
        $connection = $this->getConnection();

        if ($connection) {
            $this->defaultWorkspaceId = $connection->metadata['default_workspace_id'] ?? '';
            $this->loadWorkspaces($connection->credentials);
        }
    }

    public function getConnection(): ?Connection
    {
        return Auth::user()->connectionOfType(ConnectionType::Asana);
    }

    public function isConnected(): bool
    {
        return $this->getConnection() !== null;
    }

    public function connect(): void
    {
        $this->validate([
            'personalAccessToken' => ['required', 'string', 'min:10'],
        ]);

        $service = app(AsanaService::class, ['personalAccessToken' => $this->personalAccessToken]);

        if (! $service->validateToken()) {
            Notification::make()
                ->title('Invalid Personal Access Token')
                ->body('Could not authenticate with Asana. Please check your token and try again.')
                ->danger()
                ->send();

            return;
        }

        $workspacesResponse = $service->getWorkspaces();
        $this->workspaces = $workspacesResponse['data'] ?? [];

        $defaultWorkspaceId = ! empty($this->workspaces) ? $this->workspaces[0]['gid'] : '';

        Auth::user()->connections()->updateOrCreate(
            ['type' => ConnectionType::Asana],
            [
                'name' => 'Asana',
                'credentials' => $this->personalAccessToken,
                'metadata' => [
                    'default_workspace_id' => $defaultWorkspaceId,
                ],
                'is_active' => true,
            ],
        );

        $this->defaultWorkspaceId = $defaultWorkspaceId;
        $this->personalAccessToken = '';

        Notification::make()
            ->title('Asana connected')
            ->body('Your Asana account has been connected successfully.')
            ->success()
            ->send();
    }

    public function saveWorkspace(): void
    {
        $connection = $this->getConnection();

        if (! $connection) {
            return;
        }

        $this->validate([
            'defaultWorkspaceId' => ['required', 'string'],
        ]);

        $metadata = $connection->metadata ?? [];
        $metadata['default_workspace_id'] = $this->defaultWorkspaceId;

        $connection->update(['metadata' => $metadata]);

        Notification::make()
            ->title('Default workspace updated')
            ->success()
            ->send();
    }

    public function disconnect(): void
    {
        Auth::user()->connections()->where('type', ConnectionType::Asana)->delete();
        $this->workspaces = [];
        $this->defaultWorkspaceId = '';

        Notification::make()
            ->title('Asana disconnected')
            ->success()
            ->send();
    }

    protected function loadWorkspaces(string $token): void
    {
        try {
            $service = app(AsanaService::class, ['personalAccessToken' => $token]);
            $response = $service->getWorkspaces();
            $this->workspaces = $response['data'] ?? [];
        } catch (\Throwable) {
            $this->workspaces = [];
        }
    }
}
