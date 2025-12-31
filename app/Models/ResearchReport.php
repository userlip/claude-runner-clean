<?php

namespace App\Models;

use App\Enums\ResearchModule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ResearchReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'module',
        'title',
        'summary',
        'content',
        'findings_count',
        'proposals_created',
        'file_path',
        'task_id',
    ];

    protected function casts(): array
    {
        return [
            'module' => ResearchModule::class,
            'findings_count' => 'integer',
            'proposals_created' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ResearchReport $report) {
            $report->uuid ??= Str::uuid();
        });

        static::created(function (ResearchReport $report) {
            $report->saveToFile();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function getDefaultFilePath(): string
    {
        $date = $this->created_at?->format('Y-m-d') ?? now()->format('Y-m-d');
        $slug = Str::slug($this->module->value);

        return "docs/research/{$date}/{$slug}.md";
    }

    public function saveToFile(): void
    {
        $path = $this->file_path ?? $this->getDefaultFilePath();
        $fullPath = base_path($path);

        $directory = dirname($fullPath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $content = $this->formatAsMarkdown();
        File::put($fullPath, $content);

        if (! $this->file_path) {
            $this->updateQuietly(['file_path' => $path]);
        }
    }

    public function formatAsMarkdown(): string
    {
        $module = $this->module->label();
        $date = $this->created_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i');

        return <<<MARKDOWN
# {$this->title}

**Module:** {$module}
**Date:** {$date}
**Findings:** {$this->findings_count}
**Proposals Created:** {$this->proposals_created}

---

## Summary

{$this->summary}

---

## Full Report

{$this->content}
MARKDOWN;
    }
}
