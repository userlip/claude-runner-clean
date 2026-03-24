<?php

namespace App\Filament\Resources\McpServers\Pages;

use App\Filament\Resources\McpServers\McpServerResource;
use App\Filament\Resources\McpServers\Tables\McpServersTable;
use App\Services\McpConnectionTester;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMcpServer extends EditRecord
{
    protected static string $resource = McpServerResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
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
                    $data = $this->mutateFormDataBeforeSave($this->form->getState());
                    $record = new \App\Models\McpServer([
                        ...$this->record->attributesToArray(),
                        ...$data,
                    ]);
                    $result = app(McpConnectionTester::class)->test($record);

                    if (! $this->hasUnsavedConnectionChanges($data)) {
                        McpServersTable::persistTestResult($this->record, $result);
                    }

                    McpServersTable::sendResultNotification($result);
                }),
            DeleteAction::make(),
        ];
    }

    private function hasUnsavedConnectionChanges(array $data): bool
    {
        $fields = ['name', 'transport', 'command', 'args', 'url', 'headers', 'env_vars', 'enabled'];
        $draft = $this->record->replicate();
        $draft->syncOriginal();
        $draft->fill($data);

        return $draft->isDirty($fields);
    }
}
