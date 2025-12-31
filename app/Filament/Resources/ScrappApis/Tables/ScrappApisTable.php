<?php

namespace App\Filament\Resources\ScrappApis\Tables;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\ScrappApi;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class ScrappApisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug'),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('last_tested_at')
                    ->dateTime()
                    ->sortable()
                    ->description(fn (ScrappApi $record): ?string => $record->last_tested_at?->diffForHumans()),
                TextColumn::make('last_test_result')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'passed' => 'success',
                        'failed' => 'danger',
                        'running' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('latestTask.title')
                    ->label('Latest Task')
                    ->limit(30)
                    ->url(fn (ScrappApi $record): ?string => $record->latestTask
                        ? TaskResource::getUrl('chat', ['record' => $record->latestTask])
                        : null
                    ),
            ])
            ->defaultSort('last_tested_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                Actions\Action::make('view_chat')
                    ->label('View Chat')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (ScrappApi $record): ?string => $record->latestTask
                        ? TaskResource::getUrl('chat', ['record' => $record->latestTask])
                        : null
                    )
                    ->visible(fn (ScrappApi $record): bool => $record->latestTask !== null),

                Actions\Action::make('test_now')
                    ->label('Test Now')
                    ->icon('heroicon-o-play')
                    ->action(function (ScrappApi $record): void {
                        Artisan::call('scrappa:health-check', ['--api' => $record->id]);
                        Notification::make()
                            ->success()
                            ->title('Health check started')
                            ->send();
                    }),

                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
