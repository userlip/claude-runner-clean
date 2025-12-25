<?php

namespace App\Filament\Resources\Tasks;

use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Pages\TaskChat;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Create New Task')
                    ->schema([
                        Forms\Components\Select::make('repository_id')
                            ->label('Repository')
                            ->options(fn () => Repository::where('user_id', Auth::id())
                                ->pluck('full_name', 'id'))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('work_location', 'workspace')),

                        Forms\Components\Radio::make('work_location')
                            ->label('Work Location')
                            ->options(function (Get $get) {
                                $repoId = $get('repository_id');
                                $options = ['workspace' => 'New Workspace (fresh clone)'];

                                if ($repoId) {
                                    $sites = Site::where('repository_id', $repoId)
                                        ->where('status', 'active')
                                        ->get();

                                    foreach ($sites as $site) {
                                        $options['site_'.$site->id] = "Site: {$site->domain}";
                                    }
                                }

                                return $options;
                            })
                            ->default('workspace')
                            ->required()
                            ->visible(fn (Get $get) => $get('repository_id') !== null),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('repository.full_name')
                    ->label('Repository')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (TaskStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('workspace_path')
                    ->label('Location')
                    ->formatStateUsing(function (Task $record) {
                        if ($record->site) {
                            return $record->site->domain;
                        }

                        return 'Workspace';
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(TaskStatus::class),
            ])
            ->recordActions([
                Actions\Action::make('chat')
                    ->label('Open Chat')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (Task $record) => TaskChat::getUrl(['record' => $record])),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('repository', fn (Builder $query) => $query->where('user_id', Auth::id()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'chat' => TaskChat::route('/{record}/chat'),
        ];
    }
}
