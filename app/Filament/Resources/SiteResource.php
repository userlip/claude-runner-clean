<?php

namespace App\Filament\Resources;

use App\Enums\SiteStatus;
use App\Filament\Resources\SiteResource\Pages;
use App\Models\Repository;
use App\Models\Site;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-server-stack';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('repository', fn (Builder $query) => $query->where('user_id', Auth::id()));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Site Configuration')
                    ->schema([
                        Forms\Components\Select::make('repository_id')
                            ->label('Repository')
                            ->options(fn () => Repository::where('user_id', Auth::id())->pluck('full_name', 'id'))
                            ->required()
                            ->searchable()
                            ->default(request()->query('repository'))
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $repo = Repository::find($state);
                                    if ($repo) {
                                        $slug = Str::slug($repo->name);
                                        $set('domain', "{$slug}.marin.sh");
                                        $set('database_name', "db_{$slug}");
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('domain')
                            ->label('Domain')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('my-project.marin.sh')
                            ->helperText('Single subdomain only (e.g., feature-auth.marin.sh)'),
                    ]),

                Section::make('PHP & Server')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('php_version')
                                    ->label('PHP Version')
                                    ->options([
                                        '8.1' => 'PHP 8.1',
                                        '8.2' => 'PHP 8.2',
                                        '8.3' => 'PHP 8.3',
                                        '8.4' => 'PHP 8.4',
                                    ])
                                    ->default('8.4')
                                    ->required(),

                                Forms\Components\TextInput::make('web_directory')
                                    ->label('Web Directory')
                                    ->default('/public')
                                    ->required(),
                            ]),

                        Forms\Components\Toggle::make('isolated_user')
                            ->label('Isolated User')
                            ->helperText('Create an isolated system user for this site'),
                    ]),

                Section::make('Database')
                    ->schema([
                        Forms\Components\Toggle::make('create_database')
                            ->label('Create Database')
                            ->default(false)
                            ->live(),

                        Forms\Components\TextInput::make('database_name')
                            ->label('Database Name')
                            ->visible(fn (Get $get) => $get('create_database')),
                    ]),

                Section::make('Deployment')
                    ->schema([
                        Forms\Components\Toggle::make('run_composer')
                            ->label('Run Composer Install')
                            ->default(true),

                        Forms\Components\Textarea::make('deploy_script')
                            ->label('Deploy Script')
                            ->rows(5)
                            ->placeholder('cd {SITE_PATH}
php artisan migrate --force
php artisan config:cache'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('repository.full_name')
                    ->label('Repository')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (SiteStatus $state) => $state->color()),

                Tables\Columns\TextColumn::make('php_version')
                    ->label('PHP')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('tasks_count')
                    ->label('Tasks')
                    ->counts('tasks'),

                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Actions\Action::make('chat')
                    ->label('Open Chat')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (Site $record) => Pages\SiteChat::getUrl(['record' => $record]))
                    ->visible(fn (Site $record) => $record->isActive()),

                Actions\ViewAction::make(),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'view' => Pages\ViewSite::route('/{record}'),
            'chat' => Pages\SiteChat::route('/{record}/chat'),
        ];
    }
}
