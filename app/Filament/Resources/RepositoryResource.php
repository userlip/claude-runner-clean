<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RepositoryResource\Pages;
use App\Models\Repository;
use App\Services\GitHubService;
use App\Services\SecurityManagementService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RepositoryResource extends Resource
{
    protected static ?string $model = Repository::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Repository')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('private')
                    ->label('Visibility')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('default_branch')
                    ->label('Branch')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\ToggleColumn::make('security_management_enabled')
                    ->label('Security Mgmt')
                    ->disabled(fn (): bool => ! Auth::user()?->githubConnection),

                Tables\Columns\TextColumn::make('sites_count')
                    ->label('Sites')
                    ->counts('sites'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Synced')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                // TODO: Uncomment when SiteResource is created
                // Actions\Action::make('createSite')
                //     ->label('Create Site')
                //     ->icon('heroicon-o-server')
                //     ->color(Color::Blue)
                //     ->url(fn (Repository $record) => route('filament.admin.resources.sites.create', ['repository' => $record->id])),

                Actions\Action::make('manageEnv')
                    ->label('Manage .env')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn (Repository $record) => static::getUrl('view', ['record' => $record])),

                Actions\Action::make('installClaudeCode')
                    ->label(fn (Repository $record) => $record->claude_code_enabled ? 'Claude Code Installed' : 'Install Claude Code')
                    ->icon('heroicon-o-code-bracket')
                    ->color(fn (Repository $record) => $record->claude_code_enabled ? Color::Gray : Color::Amber)
                    ->disabled(fn (Repository $record) => $record->claude_code_enabled)
                    ->requiresConfirmation()
                    ->modalHeading('Install Claude Code')
                    ->modalDescription(fn (Repository $record) => "This will install Claude Code on {$record->full_name}:\n\n"
                        ."1. Push PR review & @claude workflow files\n"
                        ."2. Set the CLAUDE_CODE_OAUTH_TOKEN secret\n\n"
                        .'Make sure the Claude GitHub App is installed on the repo: https://github.com/apps/claude')
                    ->modalSubmitActionLabel('Install')
                    ->action(function (Repository $record): void {
                        if (! $record->user?->githubConnection) {
                            Notification::make()
                                ->title('GitHub not connected')
                                ->body('Please connect your GitHub account first.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $oauthToken = static::resolveClaudeOAuthToken();
                        if (! $oauthToken) {
                            Notification::make()
                                ->title('Claude OAuth token not found')
                                ->body('Set CLAUDE_CODE_OAUTH_TOKEN in .env or ensure ~/.claude/.credentials.json exists.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $service = new GitHubService($record->user->githubConnection);

                        if (! $service->hasRateLimitRemaining()) {
                            Notification::make()
                                ->title('GitHub rate limit reached')
                                ->body('Please try again later.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $service->setRepositorySecret(
                                $record->full_name,
                                'CLAUDE_CODE_OAUTH_TOKEN',
                                $oauthToken
                            );

                            $reviewWorkflow = file_get_contents(resource_path('github-workflows/claude-code-review.yml'));
                            $claudeWorkflow = file_get_contents(resource_path('github-workflows/claude.yml'));

                            $service->createOrUpdateFile(
                                $record->full_name,
                                '.github/workflows/claude-code-review.yml',
                                $reviewWorkflow,
                                'ci: add Claude Code automated PR review workflow',
                                $record->default_branch
                            );

                            $service->createOrUpdateFile(
                                $record->full_name,
                                '.github/workflows/claude.yml',
                                $claudeWorkflow,
                                'ci: add Claude Code interactive @claude workflow',
                                $record->default_branch
                            );

                            $record->update(['claude_code_enabled' => true]);

                            Notification::make()
                                ->title('Claude Code installed')
                                ->body("Workflows and CLAUDE_CODE_OAUTH_TOKEN pushed to {$record->full_name}.")
                                ->success()
                                ->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('Installation failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Actions\Action::make('runSecurity')
                    ->label('Run Security Check')
                    ->icon('heroicon-o-shield-check')
                    ->action(function (Repository $record): void {
                        if (! $record->user?->githubConnection) {
                            Notification::make()
                                ->title('GitHub not connected')
                                ->body('Please connect your GitHub account first.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $service = app(SecurityManagementService::class);
                        $service->processRepository($record);

                        Notification::make()
                            ->title('Security check completed')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('github')
                    ->label('GitHub')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Repository $record) => "https://github.com/{$record->full_name}")
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                Actions\Action::make('sync')
                    ->label('Sync Repositories')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function () {
                        $connection = Auth::user()->githubConnection;

                        if (! $connection) {
                            Notification::make()
                                ->title('GitHub not connected')
                                ->body('Please connect your GitHub account first.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $service = new GitHubService($connection);
                        $count = $service->syncRepositories();

                        Notification::make()
                            ->title('Repositories synced')
                            ->body("Synced {$count} repositories from GitHub.")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No repositories')
            ->emptyStateDescription('Sync your GitHub repositories to get started.')
            ->emptyStateActions([
                Actions\Action::make('sync')
                    ->label('Sync from GitHub')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function () {
                        $connection = Auth::user()->githubConnection;

                        if (! $connection) {
                            Notification::make()
                                ->title('GitHub not connected')
                                ->danger()
                                ->send();

                            return;
                        }

                        $service = new GitHubService($connection);
                        $count = $service->syncRepositories();

                        Notification::make()
                            ->title("Synced {$count} repositories")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * Resolve the Claude Code OAuth token from .env or ~/.claude/.credentials.json.
     */
    private static function resolveClaudeOAuthToken(): ?string
    {
        $envToken = config('services.anthropic.claude_code_oauth_token');
        if ($envToken) {
            return $envToken;
        }

        $credentialsPath = $_SERVER['HOME'].'/.claude/.credentials.json';
        if (! file_exists($credentialsPath)) {
            return null;
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        return $credentials['claudeAiOauth']['accessToken'] ?? null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRepositories::route('/'),
            'view' => Pages\ViewRepository::route('/{record}'),
        ];
    }
}
