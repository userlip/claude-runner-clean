<?php

namespace App\Filament\Resources\Personas\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class PersonaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('master_prompt')
                    ->required()
                    ->rows(8)
                    ->columnSpanFull(),

                Forms\Components\Select::make('repository_id')
                    ->relationship('repository', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('ai_provider_id')
                    ->relationship('aiProvider', 'display_name')
                    ->searchable()
                    ->preload(),

                Forms\Components\Textarea::make('mcp_guidance')
                    ->rows(4)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
