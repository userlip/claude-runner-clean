<?php

namespace App\Filament\Resources;

use App\Enums\GeneralChatStatus;
use App\Filament\Resources\GeneralChatResource\Pages\GeneralChatPage;
use App\Filament\Resources\GeneralChatResource\Pages\ListGeneralChats;
use App\Models\GeneralChat;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class GeneralChatResource extends Resource
{
    protected static ?string $model = GeneralChat::class;

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'General Chats';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->default('Untitled Chat')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (GeneralChatStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('messages_count')
                    ->label('Messages')
                    ->counts('messages'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(GeneralChatStatus::class),
            ])
            ->recordActions([
                Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (GeneralChat $record) => GeneralChatPage::getUrl(['record' => $record])),
                Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Actions\Action::make('create')
                    ->label('New Chat')
                    ->icon('heroicon-o-plus')
                    ->action(function () {
                        $chat = GeneralChat::create([
                            'user_id' => Auth::id(),
                        ]);

                        return redirect(GeneralChatPage::getUrl(['record' => $chat]));
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', Auth::id());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeneralChats::route('/'),
            'chat' => GeneralChatPage::route('/{record}/chat'),
        ];
    }
}
