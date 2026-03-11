<?php

namespace App\Services;

use App\Models\Persona;
use Illuminate\Support\Facades\File;

class PersonaStorageService
{
    /**
     * Initialize the full directory structure with template files for a persona.
     */
    public function initializeStorage(Persona $persona): void
    {
        $basePath = $persona->getStoragePath();

        File::ensureDirectoryExists("{$basePath}/history");
        File::ensureDirectoryExists("{$basePath}/completed-plans");

        if (! File::exists("{$basePath}/context.md")) {
            File::put("{$basePath}/context.md", $this->getDefaultContextContent($persona));
        }

        $this->syncPromptFile($persona);
    }

    /**
     * Sync the master_prompt to the prompt.md file.
     */
    public function syncPromptFile(Persona $persona): void
    {
        $basePath = $persona->getStoragePath();

        File::ensureDirectoryExists($basePath);
        File::put("{$basePath}/prompt.md", $persona->master_prompt);
    }

    /**
     * Read the context.md file for a persona.
     */
    public function readContext(Persona $persona): ?string
    {
        $path = "{$persona->getStoragePath()}/context.md";

        if (! File::exists($path)) {
            return null;
        }

        return File::get($path);
    }

    /**
     * Get the directory tree for a persona's storage.
     *
     * @return array<int, array{path: string, type: string, name: string}>
     */
    public function getDirectoryTree(Persona $persona): array
    {
        $basePath = $persona->getStoragePath();

        if (! File::isDirectory($basePath)) {
            return [];
        }

        return $this->buildTree($basePath, $basePath);
    }

    /**
     * Write content to the context.md file for a persona.
     */
    public function writeContext(Persona $persona, string $content): void
    {
        $basePath = $persona->getStoragePath();

        File::ensureDirectoryExists($basePath);
        File::put("{$basePath}/context.md", $content);
    }

    /**
     * Read a specific file from the persona's storage.
     */
    public function readFile(Persona $persona, string $relativePath): ?string
    {
        $path = "{$persona->getStoragePath()}/{$relativePath}";

        if (! File::exists($path) || ! File::isFile($path)) {
            return null;
        }

        return File::get($path);
    }

    /**
     * Get history files from the persona's history directory, newest first.
     *
     * @return array<int, array{name: string, content: string, date: string}>
     */
    public function getHistoryFiles(Persona $persona): array
    {
        $historyPath = "{$persona->getStoragePath()}/history";

        if (! File::isDirectory($historyPath)) {
            return [];
        }

        $files = collect(File::files($historyPath))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'content' => File::get($file->getPathname()),
                'date' => date('Y-m-d H:i:s', $file->getMTime()),
            ])
            ->values()
            ->all();

        return $files;
    }

    /**
     * @return array<int, array{path: string, type: string, name: string, children?: array}>
     */
    private function buildTree(string $directory, string $basePath): array
    {
        $items = [];

        foreach (File::directories($directory) as $dir) {
            $items[] = [
                'path' => str_replace($basePath.'/', '', $dir),
                'type' => 'directory',
                'name' => basename($dir),
                'children' => $this->buildTree($dir, $basePath),
            ];
        }

        foreach (File::files($directory) as $file) {
            $items[] = [
                'path' => str_replace($basePath.'/', '', $file->getPathname()),
                'type' => 'file',
                'name' => $file->getFilename(),
            ];
        }

        return $items;
    }

    private function getDefaultContextContent(Persona $persona): string
    {
        return <<<MARKDOWN
        # {$persona->name} Context

        ## Overview
        {$persona->description}

        ## Key Learnings
        <!-- Accumulated learnings from analysis cycles will appear here -->

        ## Current Focus Areas
        <!-- Focus areas will be updated after each cycle -->
        MARKDOWN;
    }
}
