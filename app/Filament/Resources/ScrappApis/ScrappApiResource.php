<?php

namespace App\Filament\Resources\ScrappApis;

use App\Filament\Resources\ScrappApis\Pages\CreateScrappApi;
use App\Filament\Resources\ScrappApis\Pages\EditScrappApi;
use App\Filament\Resources\ScrappApis\Pages\ListScrappApis;
use App\Filament\Resources\ScrappApis\Schemas\ScrappApiForm;
use App\Filament\Resources\ScrappApis\Tables\ScrappApisTable;
use App\Models\ScrappApi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ScrappApiResource extends Resource
{
    protected static ?string $model = ScrappApi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Automation';

    protected static ?string $navigationLabel = 'API Health Checks';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ScrappApiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScrappApisTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScrappApis::route('/'),
            'create' => CreateScrappApi::route('/create'),
            'edit' => EditScrappApi::route('/{record}/edit'),
        ];
    }
}
