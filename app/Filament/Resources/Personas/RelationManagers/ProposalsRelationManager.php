<?php

namespace App\Filament\Resources\Personas\RelationManagers;

use App\Enums\ProposalPriority;
use App\Enums\ProposalStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProposalsRelationManager extends RelationManager
{
    protected static string $relationship = 'proposals';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ProposalStatus $state): string => $state->color()),

                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (ProposalPriority $state): string => $state->color()),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('execution_success')
                    ->label('Execution')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        true => 'success',
                        false => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        true => 'Success',
                        false => 'Failed',
                        default => 'Pending',
                    }),

                TextColumn::make('subtasks')
                    ->label('Subtask Progress')
                    ->formatStateUsing(function ($state) {
                        if (! is_array($state) || empty($state)) {
                            return '-';
                        }

                        $completed = collect($state)->where('status', 'completed')->count();
                        $total = count($state);

                        return "{$completed}/{$total}";
                    }),
            ]);
    }
}
