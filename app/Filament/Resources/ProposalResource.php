<?php

namespace App\Filament\Resources;

use App\Enums\ProposalPriority;
use App\Enums\ProposalStatus;
use App\Filament\Resources\ProposalResource\Pages;
use App\Models\Proposal;
use App\Services\TelegramService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class ProposalResource extends Resource
{
    protected static ?string $model = Proposal::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|UnitEnum|null $navigationGroup = 'Automation';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('project')
                            ->options([
                                'scrappa' => 'Scrappa',
                                'lto2' => 'LTO2',
                                'rezensionsheld' => 'Rezensionsheld',
                                'claude_runner' => 'Claude Runner',
                            ])
                            ->required(),

                        Forms\Components\Select::make('priority')
                            ->options(ProposalPriority::class)
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options(ProposalStatus::class)
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\KeyValue::make('proposed_action')
                            ->label('Proposed Action')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('rejection_reason')
                            ->visible(fn ($record) => $record?->isRejected())
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (ProposalPriority $state): string => $state->emoji())
                    ->tooltip(fn (ProposalPriority $state): string => $state->label())
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('project')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'scrappa' => 'info',
                        'lto2' => 'success',
                        'rezensionsheld' => 'warning',
                        'claude_runner' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (ProposalStatus $state): string => $state->color())
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('approved_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ProposalStatus::class),

                Tables\Filters\SelectFilter::make('priority')
                    ->options(ProposalPriority::class),

                Tables\Filters\SelectFilter::make('project')
                    ->options([
                        'scrappa' => 'Scrappa',
                        'lto2' => 'LTO2',
                        'rezensionsheld' => 'Rezensionsheld',
                        'claude_runner' => 'Claude Runner',
                    ]),
            ])
            ->recordActions([
                Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color(Color::Green)
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to approve this proposal?')
                    ->visible(fn (Proposal $record): bool => $record->isPending())
                    ->action(function (Proposal $record): void {
                        $record->approve();

                        try {
                            app(TelegramService::class)->updateProposalMessage($record);
                        } catch (\Exception $e) {
                            // Telegram update is optional
                        }

                        Notification::make()
                            ->title('Proposal approved')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color(Color::Red)
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->visible(fn (Proposal $record): bool => $record->isPending())
                    ->action(function (Proposal $record, array $data): void {
                        $record->reject($data['rejection_reason']);

                        try {
                            app(TelegramService::class)->updateProposalMessage($record);
                        } catch (\Exception $e) {
                            // Telegram update is optional
                        }

                        Notification::make()
                            ->title('Proposal rejected')
                            ->success()
                            ->send();
                    }),

                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check')
                        ->color(Color::Green)
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->isPending()) {
                                    $record->approve();
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("{$count} proposals approved")
                                ->success()
                                ->send();
                        }),

                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProposals::route('/'),
            'create' => Pages\CreateProposal::route('/create'),
            'view' => Pages\ViewProposal::route('/{record}'),
            'edit' => Pages\EditProposal::route('/{record}/edit'),
        ];
    }
}
