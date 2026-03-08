<?php

namespace App\Filament\Resources\Personas\Tables;

use App\Enums\PersonaStatus;
use App\Models\Persona;
use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

class PersonasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('repository.name')
                    ->label('Repository')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->afterStateUpdated(function (Persona $record, bool $state): void {
                        $record->update([
                            'status' => $state ? PersonaStatus::Active : PersonaStatus::Paused,
                        ]);
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (PersonaStatus $state): string => $state->color())
                    ->icon(fn (PersonaStatus $state): string => $state->icon()),

                Tables\Columns\TextColumn::make('last_run_at')
                    ->label('Last Run')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('total_runs')
                    ->label('Runs')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_proposals')
                    ->label('Proposals')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(PersonaStatus::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                Actions\Action::make('toggle_active')
                    ->label(fn (Persona $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Persona $record): string => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Persona $record): string => $record->is_active ? 'gray' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Persona $record): void {
                        $newActive = ! $record->is_active;
                        $record->update([
                            'is_active' => $newActive,
                            'status' => $newActive ? PersonaStatus::Active : PersonaStatus::Paused,
                        ]);

                        Notification::make()
                            ->title($newActive ? 'Persona activated' : 'Persona deactivated')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
