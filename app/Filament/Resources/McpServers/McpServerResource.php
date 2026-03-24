<?php

namespace App\Filament\Resources\McpServers;

use App\Filament\Resources\McpServers\Pages\CreateMcpServer;
use App\Filament\Resources\McpServers\Pages\EditMcpServer;
use App\Filament\Resources\McpServers\Pages\ListMcpServers;
use App\Filament\Resources\McpServers\Schemas\McpServerForm;
use App\Filament\Resources\McpServers\Tables\McpServersTable;
use App\Models\McpServer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class McpServerResource extends Resource
{
    protected static ?string $model = McpServer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'MCP Servers';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return McpServerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return McpServersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMcpServers::route('/'),
            'create' => CreateMcpServer::route('/create'),
            'edit' => EditMcpServer::route('/{record}/edit'),
        ];
    }
}
