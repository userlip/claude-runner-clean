<?php

namespace App\Filament\Resources\Personas;

use App\Filament\Resources\Personas\Pages\CreatePersona;
use App\Filament\Resources\Personas\Pages\EditPersona;
use App\Filament\Resources\Personas\Pages\ListPersonas;
use App\Filament\Resources\Personas\RelationManagers\ProposalsRelationManager;
use App\Filament\Resources\Personas\Schemas\PersonaForm;
use App\Filament\Resources\Personas\Tables\PersonasTable;
use App\Filament\Resources\Personas\Widgets\PersonaStatsWidget;
use App\Models\Persona;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class PersonaResource extends Resource
{
    protected static ?string $model = Persona::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Automation';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return PersonaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PersonasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProposalsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            PersonaStatsWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersonas::route('/'),
            'create' => CreatePersona::route('/create'),
            'edit' => EditPersona::route('/{record}/edit'),
        ];
    }
}
