<?php

namespace App\Models;

use App\Services\McpServerExporter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class McpServer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'transport',
        'command',
        'args',
        'url',
        'headers',
        'env_vars',
        'enabled',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected function casts(): array
    {
        return [
            'args' => 'array',
            'headers' => 'encrypted:array',
            'env_vars' => 'encrypted:array',
            'enabled' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $server): void {
            if (! $server->shouldRefreshExport()) {
                return;
            }

            app(McpServerExporter::class)->export();
        });

        static::deleted(function (): void {
            app(McpServerExporter::class)->export();
        });
    }

    private function shouldRefreshExport(): bool
    {
        if ($this->wasRecentlyCreated) {
            return true;
        }

        return $this->wasChanged([
            'name',
            'transport',
            'command',
            'args',
            'url',
            'headers',
            'env_vars',
            'enabled',
        ]);
    }
}
