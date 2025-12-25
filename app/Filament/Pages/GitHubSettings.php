<?php

namespace App\Filament\Pages;

use App\Models\GitHubConnection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class GitHubSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'GitHub Connection';

    protected string $view = 'filament.pages.github-settings';

    public function getGitHubConnection(): ?GitHubConnection
    {
        return Auth::user()->githubConnection;
    }

    public function isConnected(): bool
    {
        return $this->getGitHubConnection() !== null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('connect')
                ->label('Connect GitHub')
                ->icon('heroicon-o-link')
                ->url(route('github.redirect'))
                ->visible(fn () => ! $this->isConnected()),

            Action::make('disconnect')
                ->label('Disconnect')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This will disconnect your GitHub account. Your synced repositories will remain.')
                ->action(function () {
                    Auth::user()->githubConnection()->delete();
                    Notification::make()
                        ->title('GitHub disconnected')
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->isConnected()),
        ];
    }
}
