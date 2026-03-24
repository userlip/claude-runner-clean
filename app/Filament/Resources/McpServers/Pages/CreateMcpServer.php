<?php

namespace App\Filament\Resources\McpServers\Pages;

use App\Filament\Resources\McpServers\McpServerResource;
use App\Filament\Resources\McpServers\Tables\McpServersTable;
use App\Models\McpServer;
use App\Services\McpConnectionTester;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateMcpServer extends CreateRecord
{
    protected static string $resource = McpServerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['transport'] === 'sse') {
            $data['command'] = null;
            $data['args'] = [];
            $data['env_vars'] = [];
        }

        if ($data['transport'] === 'command') {
            $data['url'] = null;
            $data['headers'] = [];
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_connection')
                ->label('Test Connection')
                ->icon('heroicon-o-play')
                ->action(function (): void {
                    $data = $this->form->getState();
                    $record = new McpServer($data);
                    $result = app(McpConnectionTester::class)->test($record);

                    McpServersTable::sendResultNotification($result);
                }),
        ];
    }
}
