<?php

namespace App\Filament\Resources\McpServers\Tables;

use App\Models\McpServer;
use App\Services\McpConnectionTester;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class McpServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('transport')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'command' ? 'info' : 'warning'),

                TextColumn::make('enabled')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Enabled' : 'Disabled')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                IconColumn::make('last_test_status')
                    ->label('Connection')
                    ->icon(fn (?string $state): string => match ($state) {
                        'success' => 'heroicon-o-check-circle',
                        'failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-clock',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('last_tested_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                Actions\Action::make('test_connection')
                    ->label('Test')
                    ->icon('heroicon-o-play')
                    ->action(function (McpServer $record): void {
                        $result = app(McpConnectionTester::class)->test($record);

                        static::persistTestResult($record, $result);
                        static::sendResultNotification($result);
                    }),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkAction::make('test_all')
                    ->label('Test All')
                    ->icon('heroicon-o-bolt')
                    ->action(function (Collection $records): void {
                        $tester = app(McpConnectionTester::class);

                        foreach ($records as $record) {
                            $result = $tester->test($record);
                            static::persistTestResult($record, $result);
                        }

                        Notification::make()
                            ->title('Connection tests completed')
                            ->success()
                            ->send();
                    }),
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  array{status: string, message: string, successful: bool}  $result
     */
    public static function persistTestResult(McpServer $record, array $result): void
    {
        $record->forceFill([
            'last_tested_at' => now(),
            'last_test_status' => $result['status'],
            'last_test_message' => $result['message'],
        ])->save();
    }

    /**
     * @param  array{status: string, message: string, successful: bool}  $result
     */
    public static function sendResultNotification(array $result): void
    {
        Notification::make()
            ->title($result['successful'] ? 'Connection test succeeded' : 'Connection test failed')
            ->body($result['message'])
            ->color($result['successful'] ? 'success' : 'danger')
            ->send();
    }
}
