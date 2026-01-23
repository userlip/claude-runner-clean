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
                        $service->ensureSecurityTask($record);
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRepositories::route('/'),
            'view' => Pages\ViewRepository::route('/{record}'),
        ];
    }
}
