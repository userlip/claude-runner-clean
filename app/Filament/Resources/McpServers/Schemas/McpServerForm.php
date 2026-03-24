<?php

namespace App\Filament\Resources\McpServers\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class McpServerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Server Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\Select::make('transport')
                            ->options([
                                'command' => 'Command',
                                'sse' => 'SSE',
                            ])
                            ->required()
                            ->default('command')
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if ($state === 'command') {
                                    $set('url', null);
                                    $set('headers', []);

                                    return;
                                }

                                if ($state === 'sse') {
                                    $set('command', null);
                                    $set('args', []);
                                    $set('env_vars', []);
                                }
                            }),

                        Forms\Components\Toggle::make('enabled')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),

                Section::make('Command Transport')
                    ->schema([
                        Forms\Components\TextInput::make('command')
                            ->required(fn (Get $get): bool => $get('transport') === 'command')
                            ->visible(fn (Get $get): bool => $get('transport') === 'command')
                            ->maxLength(255),

                        Forms\Components\TagsInput::make('args')
                            ->visible(fn (Get $get): bool => $get('transport') === 'command')
                            ->helperText('Each item will be passed as a separate CLI argument.')
                            ->default([]),

                        Forms\Components\KeyValue::make('env_vars')
                            ->visible(fn (Get $get): bool => $get('transport') === 'command')
                            ->default([])
                            ->keyLabel('Variable')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('SSE Transport')
                    ->schema([
                        Forms\Components\TextInput::make('url')
                            ->url()
                            ->required(fn (Get $get): bool => $get('transport') === 'sse')
                            ->visible(fn (Get $get): bool => $get('transport') === 'sse')
                            ->maxLength(255),

                        Forms\Components\KeyValue::make('headers')
                            ->visible(fn (Get $get): bool => $get('transport') === 'sse')
                            ->default([])
                            ->keyLabel('Header')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
