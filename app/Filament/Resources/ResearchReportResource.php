<?php

namespace App\Filament\Resources;

use App\Enums\ResearchModule;
use App\Filament\Resources\ResearchReportResource\Pages;
use App\Models\ResearchReport;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class ResearchReportResource extends Resource
{
    protected static ?string $model = ResearchReport::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Research';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Select::make('module')
                            ->options(ResearchModule::class)
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('findings_count')
                            ->label('Findings Count')
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('proposals_created')
                            ->label('Proposals Created')
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('title')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('summary')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\MarkdownEditor::make('content')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->disabled()
                            ->columnSpan(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('module')
                    ->badge()
                    ->color(fn (ResearchModule $state): string => $state->color())
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50)
                    ->sortable(),

                Tables\Columns\TextColumn::make('summary')
                    ->limit(80)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('findings_count')
                    ->label('Findings')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposals_created')
                    ->label('Proposals')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('module')
                    ->options(ResearchModule::class),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResearchReports::route('/'),
            'view' => Pages\ViewResearchReport::route('/{record}'),
        ];
    }
}
