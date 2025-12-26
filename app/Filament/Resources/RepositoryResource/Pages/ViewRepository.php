<?php

namespace App\Filament\Resources\RepositoryResource\Pages;

use App\Filament\Resources\RepositoryResource;
use App\Models\RepositoryEnvConfig;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ViewRepository extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = RepositoryResource::class;

    protected string $view = 'filament.resources.repository-resource.pages.view-repository';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->record->full_name;
    }

    public function getBreadcrumb(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('github')
                ->label('View on GitHub')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url("https://github.com/{$this->record->full_name}")
                ->openUrlInNewTab(),

            Actions\Action::make('addEnvConfig')
                ->label('Add Env Config')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->placeholder('e.g., Local, Production, Staging')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('content')
                        ->label('.env Content')
                        ->placeholder("APP_NAME=MyApp\nAPP_ENV=local\n...")
                        ->required()
                        ->rows(15)
                        ->extraAttributes(['class' => 'font-mono text-sm']),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Set as default')
                        ->helperText('This config will be auto-copied when creating new tasks'),
                ])
                ->action(function (array $data): void {
                    $config = $this->record->envConfigs()->create($data);

                    if ($data['is_default']) {
                        $config->setAsDefault();
                    }

                    Notification::make()
                        ->title('Env config created')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => RepositoryEnvConfig::query()->where('repository_id', $this->record->id))
            ->columns([
                Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning'),

                Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Actions\Action::make('setDefault')
                    ->label('Set Default')
                    ->icon('heroicon-o-star')
                    ->visible(fn (RepositoryEnvConfig $record): bool => ! $record->is_default)
                    ->action(function (RepositoryEnvConfig $record): void {
                        $record->setAsDefault();

                        Notification::make()
                            ->title("'{$record->name}' is now the default")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('content')
                            ->label('.env Content')
                            ->required()
                            ->rows(15)
                            ->extraAttributes(['class' => 'font-mono text-sm']),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Set as default'),
                    ])
                    ->fillForm(fn (RepositoryEnvConfig $record): array => [
                        'name' => $record->name,
                        'content' => $record->content,
                        'is_default' => $record->is_default,
                    ])
                    ->action(function (RepositoryEnvConfig $record, array $data): void {
                        $record->update($data);

                        if ($data['is_default']) {
                            $record->setAsDefault();
                        }

                        Notification::make()
                            ->title('Env config updated')
                            ->success()
                            ->send();
                    }),

                Actions\DeleteAction::make()
                    ->before(function (RepositoryEnvConfig $record, Actions\DeleteAction $action): void {
                        $count = $record->repository->envConfigs()->count();
                        if ($count === 1) {
                            Notification::make()
                                ->title('Cannot delete')
                                ->body('This is the only env config for this repository.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->emptyStateHeading('No env configs')
            ->emptyStateDescription('Add your first .env configuration for this repository.')
            ->emptyStateActions([
                Actions\Action::make('addFirst')
                    ->label('Add Env Config')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->placeholder('e.g., Local')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('content')
                            ->label('.env Content')
                            ->required()
                            ->rows(15)
                            ->extraAttributes(['class' => 'font-mono text-sm']),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Set as default')
                            ->default(true),
                    ])
                    ->action(function (array $data): void {
                        $config = $this->record->envConfigs()->create($data);

                        if ($data['is_default']) {
                            $config->setAsDefault();
                        }

                        Notification::make()
                            ->title('Env config created')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
