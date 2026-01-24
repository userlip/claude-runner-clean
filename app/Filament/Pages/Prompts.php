<?php

namespace App\Filament\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use UnitEnum;

class Prompts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.prompts';

    public static function canAccess(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->getPromptRecords())
            ->columns([
                TextColumn::make('id')
                    ->label('Path')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group')
                    ->label('Group')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        Forms\Components\TextInput::make('path')
                            ->label('Path')
                            ->disabled(),
                        Forms\Components\Textarea::make('content')
                            ->label('Content')
                            ->rows(24)
                            ->extraAttributes(['class' => 'font-mono text-sm'])
                            ->required(),
                    ])
                    ->fillForm(function ($record): array {
                        $id = is_array($record) ? ($record['id'] ?? '') : (string) $record;

                        if ($id === '') {
                            return [
                                'path' => '',
                                'content' => '',
                            ];
                        }

                        $path = $this->promptPath($id);

                        return [
                            'path' => $id,
                            'content' => File::exists($path) ? File::get($path) : '',
                        ];
                    })
                    ->action(function (array $data): void {
                        $path = $this->promptPath($data['path']);
                        File::put($path, $data['content']);

                        Notification::make()
                            ->title('Prompt updated')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Actions\Action::make('create')
                    ->label('New Prompt')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\TextInput::make('path')
                            ->label('Path (relative to resources/prompts)')
                            ->placeholder('security/new-prompt.md')
                            ->required(),
                        Forms\Components\Textarea::make('content')
                            ->label('Content')
                            ->rows(24)
                            ->extraAttributes(['class' => 'font-mono text-sm'])
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $relative = trim($data['path']);
                        $path = $this->promptPath($relative, allowMissing: true);
                        $directory = dirname($path);

                        if (! File::isDirectory($directory)) {
                            File::makeDirectory($directory, 0755, true);
                        }

                        File::put($path, $data['content']);

                        Notification::make()
                            ->title('Prompt created')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getPromptRecords(): array
    {
        $base = resource_path('prompts');

        if (! File::isDirectory($base)) {
            return [];
        }

        return collect(File::allFiles($base))
            ->filter(fn ($file) => $file->isFile())
            ->map(function ($file) use ($base): array {
                $relative = ltrim(Str::replaceFirst($base, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                $group = trim(Str::replaceFirst($base, '', $file->getPath()), DIRECTORY_SEPARATOR) ?: 'root';

                return [
                    'id' => $relative,
                    'name' => $file->getFilename(),
                    'group' => $group,
                    'updated_at' => Carbon::createFromTimestamp($file->getMTime()),
                ];
            })
            ->sortBy('id')
            ->values()
            ->all();
    }

    protected function promptPath(string $relative, bool $allowMissing = false): string
    {
        $relative = trim($relative);

        if ($relative === '' || Str::contains($relative, '..') || Str::startsWith($relative, ['/', '\\'])) {
            throw new \InvalidArgumentException('Invalid prompt path.');
        }

        $base = resource_path('prompts');
        $path = $base.DIRECTORY_SEPARATOR.$relative;

        if (! $allowMissing) {
            $realBase = realpath($base) ?: $base;
            $realPath = realpath($path);

            if (! $realPath || ! Str::startsWith($realPath, $realBase)) {
                throw new \InvalidArgumentException('Prompt path not found.');
            }
        }

        return $path;
    }
}
