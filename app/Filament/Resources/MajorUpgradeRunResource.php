<?php

namespace App\Filament\Resources;

use App\Enums\MajorUpgradeStatus;
use App\Filament\Resources\MajorUpgradeRunResource\Pages;
use App\Models\MajorUpgradeRun;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MajorUpgradeRunResource extends Resource
{
    protected static ?string $model = MajorUpgradeRun::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Automation';

    protected static ?string $navigationLabel = 'Major Upgrades';

    protected static ?int $navigationSort = 7;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('repository.full_name')
                    ->label('Repository')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('github_pr_number')
                    ->label('PR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (MajorUpgradeStatus|string $state): string => match ($state instanceof MajorUpgradeStatus ? $state->value : $state) {
                        'completed' => 'success',
                        'pr_opened' => 'info',
                        'review_site_created' => 'info',
                        'failed' => 'danger',
                        'pending' => 'gray',
                        'researching' => 'gray',
                        'upgrading' => 'warning',
                        'fixing' => 'warning',
                        'testing' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('review_site_url')
                    ->label('Review Site')
                    ->url(fn (?string $state) => $state, true)
                    ->openUrlInNewTab()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMajorUpgradeRuns::route('/'),
        ];
    }
}
