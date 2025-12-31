<?php

namespace App\Filament\Resources\ScrappApis\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ScrappApiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('route_prefix')
                    ->required()
                    ->helperText('e.g., api/google-maps'),
                TextInput::make('rapidapi_slug')
                    ->helperText('e.g., google-maps-scraper'),
                Toggle::make('is_active')
                    ->default(true),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
