<?php

namespace App\Livewire;

use Illuminate\Support\Facades\File;
use Livewire\Attributes\Computed;
use Livewire\Component;

class FileBrowser extends Component
{
    public string $basePath;

    /** @var array<string> */
    public array $expandedDirs = [];

    public ?string $selectedFile = null;

    public ?string $fileContent = null;

    /** @var array<string> */
    protected array $ignoredDirs = [
        'node_modules',
        'vendor',
        '.git',
        '.idea',
        '.vscode',
        'storage',
        'bootstrap/cache',
    ];

    public function mount(string $basePath): void
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * @return array<array{name: string, path: string, isDir: bool, extension: string|null}>
     */
    #[Computed]
    public function files(): array
    {
        return $this->getFilesInDirectory($this->basePath);
    }

    /**
     * @return array<array{name: string, path: string, isDir: bool, extension: string|null}>
     */
    public function getFilesInDirectory(string $path): array
    {
        if (! File::isDirectory($path)) {
            return [];
        }

        $items = [];
        $contents = File::directories($path);

        // Add directories first
        foreach ($contents as $dir) {
            $name = basename($dir);
            if (in_array($name, $this->ignoredDirs)) {
                continue;
            }

            $relativePath = $this->getRelativePath($dir);
            $items[] = [
                'name' => $name,
                'path' => $relativePath,
                'isDir' => true,
                'extension' => null,
            ];
        }

        // Add files
        foreach (File::files($path) as $file) {
            $name = $file->getFilename();
            $items[] = [
                'name' => $name,
                'path' => $this->getRelativePath($file->getPathname()),
                'isDir' => false,
                'extension' => $file->getExtension(),
            ];
        }

        return $items;
    }

    public function toggleDirectory(string $path): void
    {
        if (in_array($path, $this->expandedDirs)) {
            $this->expandedDirs = array_values(array_diff($this->expandedDirs, [$path]));
        } else {
            $this->expandedDirs[] = $path;
        }
    }

    public function isExpanded(string $path): bool
    {
        return in_array($path, $this->expandedDirs);
    }

    public function selectFile(string $path): void
    {
        $fullPath = $this->basePath.'/'.$path;
        $realPath = realpath($fullPath);

        // Security: ensure we're still within basePath
        if (! $realPath || ! str_starts_with($realPath, $this->basePath)) {
            $this->selectedFile = null;
            $this->fileContent = null;

            return;
        }

        if (! File::isFile($realPath)) {
            $this->selectedFile = null;
            $this->fileContent = null;

            return;
        }

        $this->selectedFile = $path;

        // Limit file size for preview (100KB)
        $size = File::size($realPath);
        if ($size > 102400) {
            $this->fileContent = '[File too large to preview]';
        } else {
            $this->fileContent = File::get($realPath);
        }
    }

    public function closePreview(): void
    {
        $this->selectedFile = null;
        $this->fileContent = null;
    }

    private function getRelativePath(string $fullPath): string
    {
        return ltrim(str_replace($this->basePath, '', $fullPath), '/');
    }

    public function getFileIcon(string $extension): string
    {
        return match ($extension) {
            'php' => 'heroicon-o-code-bracket',
            'js', 'ts', 'jsx', 'tsx' => 'heroicon-o-code-bracket-square',
            'json' => 'heroicon-o-document-text',
            'md' => 'heroicon-o-document',
            'css', 'scss' => 'heroicon-o-paint-brush',
            'vue' => 'heroicon-o-squares-2x2',
            'blade.php' => 'heroicon-o-document-text',
            default => 'heroicon-o-document',
        };
    }

    public function render()
    {
        return view('livewire.file-browser');
    }
}
