<?php

namespace App\Filament\Pages;

use App\Enums\ConnectionType;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use UnitEnum;

class GoogleAnalyticsSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Google Analytics';

    protected static ?string $title = 'Google Analytics Connections';

    protected static ?string $slug = 'google-analytics-settings';

    protected string $view = 'filament.pages.google-analytics-settings';

    public string $newConnectionName = '';

    public string $newPropertyId = '';

    /** @var TemporaryUploadedFile|null */
    public $credentialsFile = null;

    public function mount(): void
    {
        $this->form->fill([
            'newConnectionName' => $this->newConnectionName,
            'newPropertyId' => $this->newPropertyId,
            'credentialsFile' => $this->credentialsFile,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('newConnectionName')
                    ->label('Account Name')
                    ->placeholder('e.g. My Client, Main Website')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('credentialsFile')
                    ->label('Service Account JSON Key File')
                    ->acceptedFileTypes(['application/json', 'text/json'])
                    ->maxFiles(1)
                    ->storeFiles(false)
                    ->required()
                    ->helperText('Upload the JSON key file downloaded from Google Cloud Console (step 4 in the setup guide above).'),
                TextInput::make('newPropertyId')
                    ->label('Default Property ID (optional)')
                    ->placeholder('e.g. properties/123456789')
                    ->maxLength(255)
                    ->helperText('Find this in Google Analytics under Admin > Property Settings (step 6 in the setup guide above).'),
            ]);
    }

    public function getConnections(): Collection
    {
        return Auth::user()->googleAnalyticsConnections()->orderBy('name')->get();
    }

    public function addConnection(): void
    {
        $this->validate([
            'newConnectionName' => ['required', 'string', 'max:255'],
            'credentialsFile' => ['required'],
            'newPropertyId' => ['nullable', 'string', 'max:255'],
        ]);

        $file = is_array($this->credentialsFile) ? reset($this->credentialsFile) : $this->credentialsFile;
        $credentialsJson = $file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
            ? $file->get()
            : file_get_contents($file->getRealPath());

        $decoded = json_decode($credentialsJson, true);
        if (! $decoded || ! isset($decoded['type']) || $decoded['type'] !== 'service_account') {
            Notification::make()
                ->title('Invalid credentials file')
                ->body('The file must be a Google service account JSON key file.')
                ->danger()
                ->send();

            return;
        }

        Auth::user()->connections()->create([
            'type' => ConnectionType::GoogleAnalytics,
            'name' => $this->newConnectionName,
            'credentials' => $credentialsJson,
            'metadata' => [
                'property_id' => $this->newPropertyId ?: null,
            ],
            'is_active' => true,
        ]);

        $this->reset(['newConnectionName', 'credentialsFile', 'newPropertyId']);

        Notification::make()
            ->title('Google Analytics connection added')
            ->success()
            ->send();
    }

    public function deleteConnection(int $connectionId): void
    {
        Auth::user()->googleAnalyticsConnections()->findOrFail($connectionId)->delete();

        Notification::make()
            ->title('Connection removed')
            ->success()
            ->send();
    }
}
