<?php

namespace App\Filament\Resources;

use App\Enums\DirectoryCategory;
use App\Filament\Resources\PromotionDirectoryResource\Pages;
use App\Models\PromotionDirectory;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PromotionDirectoryResource extends Resource
{
    protected static ?string $model = PromotionDirectory::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|UnitEnum|null $navigationGroup = 'Research';

    protected static ?int $navigationSort = 2;

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
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('url')
                            ->required()
                            ->url()
                            ->maxLength(255)
                            ->columnSpan(1),

                        Forms\Components\Select::make('category')
                            ->options(DirectoryCategory::class)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Select::make('submission_type')
                            ->options([
                                'free' => 'Free',
                                'paid' => 'Paid',
                                'invite_only' => 'Invite Only',
                            ])
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('submission_url')
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('requirements')
                            ->label('Requirements')
                            ->keyLabel('Requirement')
                            ->valueLabel('Details')
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('suitable_products')
                            ->label('Suitable Products')
                            ->suggestions([
                                'scrappa',
                                'lto2',
                                'rezensionsheld',
                                'claude_runner',
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->limit(30)
                    ->sortable(),

                Tables\Columns\TextColumn::make('url')
                    ->limit(40)
                    ->url(fn (PromotionDirectory $record): string => $record->url)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (DirectoryCategory $state): string => $state->color())
                    ->sortable(),

                Tables\Columns\TextColumn::make('submission_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'free' => 'success',
                        'paid' => 'warning',
                        'invite_only' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('submissions_count')
                    ->counts('submissions')
                    ->label('Submissions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(DirectoryCategory::class),

                Tables\Filters\SelectFilter::make('submission_type')
                    ->options([
                        'free' => 'Free',
                        'paid' => 'Paid',
                        'invite_only' => 'Invite Only',
                    ]),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
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
            'index' => Pages\ListPromotionDirectories::route('/'),
            'create' => Pages\CreatePromotionDirectory::route('/create'),
            'edit' => Pages\EditPromotionDirectory::route('/{record}/edit'),
        ];
    }
}
