<?php

namespace App\Filament\Resources\Repositories;

use App\Enums\ValueTier;
use App\Filament\Resources\Repositories\Pages\EditRepository;
use App\Filament\Resources\Repositories\Pages\ListRepositories;
use App\Models\Repository;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class RepositoryResource extends Resource
{
    protected static ?string $model = Repository::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-folder';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->disabled(),
                        Forms\Components\TextInput::make('full_name')
                            ->disabled(),
                        Forms\Components\TextInput::make('project_key')
                            ->helperText('Used to link proposals to this repository'),
                        Forms\Components\Toggle::make('security_management_enabled')
                            ->label('Security Management')
                            ->helperText('Enable auto-merge + deploy for Dependabot PRs'),
                        Forms\Components\TextInput::make('ploi_url')
                            ->label('Ploi URL')
                            ->placeholder('https://ploi.io/servers/12345/sites/67890')
                            ->helperText('Paste a Ploi URL to auto-fill server and site IDs')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                if (! $state) {
                                    return;
                                }

                                // Parse URL like: https://ploi.io/servers/12345/sites/67890
                                if (preg_match('#/servers/(\d+)/sites/(\d+)#', $state, $matches)) {
                                    $set('ploi_server_id', $matches[1]);
                                    $set('ploi_site_id', $matches[2]);
                                }
                            }),
                        Forms\Components\TextInput::make('ploi_server_id')
                            ->label('Ploi Server ID'),
                        Forms\Components\TextInput::make('ploi_site_id')
                            ->label('Ploi Site ID'),
                        Forms\Components\Select::make('value_tier')
                            ->options(ValueTier::class)
                            ->required()
                            ->helperText('Higher value = higher research priority'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('project_key')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('value_tier')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tasks_count')
                    ->counts('tasks')
                    ->label('Tasks')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('value_tier')
                    ->options(ValueTier::class),
            ])
            ->defaultSort('value_tier', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRepositories::route('/'),
            'edit' => EditRepository::route('/{record}/edit'),
        ];
    }
}
