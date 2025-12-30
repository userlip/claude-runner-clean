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

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Chats';

    protected static ?string $pluralModelLabel = 'Chats';

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
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->placeholder('Untitled')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('repository.full_name')
                    ->label('Repository')
                    ->placeholder('General Chat')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (TaskStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('workspace_path')
                    ->label('Location')
                    ->formatStateUsing(function (Task $record) {
                        if ($record->isGeneralChat()) {
                            return '/home/ploi';
                        }

                        if ($record->site) {
                            return $record->site->domain;
                        }

                        return 'Workspace';
                    }),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->label('Last Message')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('No messages'),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->recordUrl(fn (Task $record) => TaskChat::getUrl(['record' => $record]))
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(TaskStatus::class),
                Tables\Filters\TernaryFilter::make('is_general_chat')
                    ->label('Type')
                    ->placeholder('All')
                    ->trueLabel('General Chats')
                    ->falseLabel('Repository Tasks')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('repository_id'),
                        false: fn (Builder $query) => $query->whereNotNull('repository_id'),
                    ),
            ])
            ->headerActions([
                Actions\Action::make('new_chat')
                    ->label('New Chat')
                    ->icon('heroicon-o-plus')
                    ->action(function () {
                        $task = Task::create([
                            'user_id' => Auth::id(),
                        ]);

                        return redirect(TaskChat::getUrl(['record' => $task]));
                    }),
            ])
            ->recordActions([
                Actions\Action::make('chat')
                    ->label('Open Chat')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (Task $record) => TaskChat::getUrl(['record' => $record])),
                Actions\DeleteAction::make()
                    ->modalDescription(fn (Task $record) => $record->isInWorkspace()
                        ? "This will permanently delete the chat and its workspace directory:\n{$record->workspace_path}"
                        : 'This will permanently delete the chat and all its messages.'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query) {
                // Tasks with repositories owned by the user
                $query->whereHas('repository', fn (Builder $q) => $q->where('user_id', Auth::id()))
                    // OR general chats owned by the user
                    ->orWhere('user_id', Auth::id());
            });
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
