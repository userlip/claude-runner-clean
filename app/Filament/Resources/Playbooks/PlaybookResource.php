<?php

namespace App\Filament\Resources\Playbooks;

use App\Enums\ProposalType;
use App\Filament\Resources\Playbooks\Pages\CreatePlaybook;
use App\Filament\Resources\Playbooks\Pages\EditPlaybook;
use App\Filament\Resources\Playbooks\Pages\ListPlaybooks;
use App\Models\Playbook;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PlaybookResource extends Resource
{
    protected static ?string $model = Playbook::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|UnitEnum|null $navigationGroup = 'Automation';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('proposal_type')
                            ->options(ProposalType::class)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $type = ProposalType::from($state);
                                    $set('skills', $type->getRequiredSkills());
                                }
                            }),

                        Forms\Components\Select::make('project')
                            ->options([
                                'scrappa' => 'Scrappa',
                                'lto2' => 'LTO2',
                                'rezensionsheld' => 'Rezensionsheld',
                                'claude_runner' => 'Claude Runner',
                            ])
                            ->placeholder('All projects')
                            ->helperText('Leave empty for generic playbook'),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('prompt_template')
                            ->required()
                            ->rows(10)
                            ->columnSpanFull()
                            ->helperText('Placeholders: {{TITLE}}, {{DESCRIPTION}}, {{PROJECT}}, {{TYPE}}'),

                        Forms\Components\TagsInput::make('skills')
                            ->placeholder('Add skill')
                            ->helperText('Skills to invoke during execution')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
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
                Tables\Columns\TextColumn::make('proposal_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('project')
                    ->badge()
                    ->color('gray')
                    ->placeholder('All'),
                Tables\Columns\TextColumn::make('times_used')
                    ->label('Used')
                    ->sortable(),
                Tables\Columns\TextColumn::make('success_rate')
                    ->label('Success')
                    ->getStateUsing(fn (Playbook $record) => $record->getSuccessRate().'%')
                    ->sortable(query: fn ($query, $direction) => $query
                        ->orderByRaw("CASE WHEN times_used = 0 THEN 0 ELSE (success_count * 100.0 / times_used) END {$direction}")
                    ),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('proposal_type')
                    ->options(ProposalType::class),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->default(true),
            ])
            ->recordActions([
                Actions\EditAction::make(),
            ])
            ->defaultSort('times_used', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlaybooks::route('/'),
            'create' => CreatePlaybook::route('/create'),
            'edit' => EditPlaybook::route('/{record}/edit'),
        ];
    }
}
