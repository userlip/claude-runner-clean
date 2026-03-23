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

class SearchConsoleSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Search Console';

    protected static ?string $title = 'Google Search Console Connections';

    protected static ?string $slug = 'search-console-settings';

    protected string $view = 'filament.pages.search-console-settings';

    public string $newConnectionName = '';

    /** @var TemporaryUploadedFile|null */
    public $credentialsFile = null;

    public function mount(): void
    {
        $this->form->fill([
            'newConnectionName' => $this->newConnectionName,
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
                    ->helperText('Upload the JSON key file downloaded from Google Cloud Console. You can reuse the same file from Google Analytics if it is the same service account.'),
            ]);
    }

    public function getConnections(): Collection
    {
        return Auth::user()->searchConsoleConnections()->orderBy('name')->get();
    }

    public function addConnection(): void
    {
        $this->validate([
            'newConnectionName' => ['required', 'string', 'max:255'],
            'credentialsFile' => ['required'],
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
            'type' => ConnectionType::SearchConsole,
            'name' => $this->newConnectionName,
            'credentials' => $credentialsJson,
            'is_active' => true,
        ]);

        $this->reset(['newConnectionName', 'credentialsFile']);

        Notification::make()
            ->title('Search Console connection added')
            ->success()
            ->send();
    }

    public function deleteConnection(int $connectionId): void
    {
        Auth::user()->searchConsoleConnections()->findOrFail($connectionId)->delete();

        Notification::make()
            ->title('Connection removed')
            ->success()
            ->send();
    }
}
