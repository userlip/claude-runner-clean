<?php

namespace App\Models;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory;

    /**
     * Maximum number of blocks to show before collapsing.
     */
    public const MAX_VISIBLE_BLOCKS = 10;

    protected static function booted(): void
    {
        static::created(function (Message $message) {
            $message->task?->update(['last_message_at' => $message->created_at]);
        });
    }

    protected $fillable = [
        'task_id',
        'role',
        'status',
        'content',
        'content_blocks',
        'images',
        'raw_output',
        'tool_calls',
        'tokens_in',
        'tokens_out',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'status' => MessageStatus::class,
            'content_blocks' => 'array',
            'images' => 'array',
            'tool_calls' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'cost_usd' => 'decimal:6',
        ];
    }

    public function isQueued(): bool
    {
        return $this->status === MessageStatus::Queued;
    }

    public function isSent(): bool
    {
        return $this->status === MessageStatus::Sent;
    }

    public function markAsSent(): void
    {
        $this->update(['status' => MessageStatus::Sent]);
    }

    public function addContentBlock(array $block): void
    {
        $blocks = $this->content_blocks ?? [];
        $blocks[] = $block;
        $this->update(['content_blocks' => $blocks]);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isFromUser(): bool
    {
        return $this->role === MessageRole::User;
    }

    public function isFromAssistant(): bool
    {
        return $this->role === MessageRole::Assistant;
    }

    public function appendRawOutput(string $chunk): void
    {
        $this->update([
            'raw_output' => ($this->raw_output ?? '').$chunk,
        ]);
    }

    public function addToolCall(array $toolCall): void
    {
        $calls = $this->tool_calls ?? [];
        $calls[] = $toolCall;
        $this->update(['tool_calls' => $calls]);
    }

    /**
     * Get grouped content blocks (tool calls combined, text blocks separate).
     * This is cached to avoid re-processing on every render.
     *
     * @return array{firstBlockIsText: bool, hasNoContentBlocks: bool, groupedBlocks: array, totalBlockCount: int}
     */
    public function getGroupedBlocks(): array
    {
        // Only process assistant messages with content blocks
        if (! $this->isFromAssistant() || ! $this->content_blocks || count($this->content_blocks) === 0) {
            return [
                'firstBlockIsText' => false,
                'hasNoContentBlocks' => true,
                'groupedBlocks' => [],
                'totalBlockCount' => 0,
            ];
        }

        // Cache based on message ID and content_blocks hash
        $cacheKey = "message_grouped_blocks_{$this->id}_".md5(json_encode($this->content_blocks));

        return Cache::remember($cacheKey, now()->addHours(24), function () {
            $blocks = collect($this->content_blocks);
            $firstBlock = $blocks->first();

            $firstBlockIsText = ($firstBlock['type'] ?? '') === 'text' && ! empty($firstBlock['text']);

            // Skip first block only if it was a text block (already rendered separately)
            $blocksToProcess = $firstBlockIsText ? $blocks->skip(1)->values() : $blocks->values();

            $groupedBlocks = [];
            $currentToolGroup = [];

            foreach ($blocksToProcess as $block) {
                if (($block['type'] ?? '') === 'tool_use') {
                    $toolName = $block['tool']['name'] ?? '';
                    // AskUserQuestion gets its own block type for special rendering
                    if ($toolName === 'AskUserQuestion') {
                        if (count($currentToolGroup) > 0) {
                            $groupedBlocks[] = ['type' => 'tool_group', 'tools' => $currentToolGroup];
                            $currentToolGroup = [];
                        }
                        $groupedBlocks[] = [
                            'type' => 'ask_user_question',
                            'tool' => $block['tool'],
                            'timestamp' => $block['timestamp'] ?? null,
                        ];
                    } else {
                        $currentToolGroup[] = $block;
                    }
                } else {
                    if (count($currentToolGroup) > 0) {
                        $groupedBlocks[] = ['type' => 'tool_group', 'tools' => $currentToolGroup];
                        $currentToolGroup = [];
                    }
                    $groupedBlocks[] = $block;
                }
            }

            if (count($currentToolGroup) > 0) {
                $groupedBlocks[] = ['type' => 'tool_group', 'tools' => $currentToolGroup];
            }

            return [
                'firstBlockIsText' => $firstBlockIsText,
                'hasNoContentBlocks' => false,
                'groupedBlocks' => $groupedBlocks,
                'totalBlockCount' => count($groupedBlocks),
            ];
        });
    }

    /**
     * Render markdown content and cache the result.
     */
    public function renderMarkdown(string $text): string
    {
        $cacheKey = 'markdown_'.md5($text);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($text) {
            return Str::markdown($text);
        });
    }

    /**
     * Get the first text block's rendered HTML if available.
     */
    public function getFirstTextBlockHtml(): ?string
    {
        $grouped = $this->getGroupedBlocks();
        if (! $grouped['firstBlockIsText']) {
            return null;
        }

        $firstBlock = $this->content_blocks[0] ?? null;
        if (! $firstBlock || empty($firstBlock['text'])) {
            return null;
        }

        return $this->renderMarkdown($firstBlock['text']);
    }

    /**
     * Check if this message has many content blocks (should be collapsed).
     */
    public function hasLongContent(): bool
    {
        $grouped = $this->getGroupedBlocks();

        return $grouped['totalBlockCount'] > self::MAX_VISIBLE_BLOCKS;
    }

    /**
     * Get count of hidden blocks when collapsed.
     */
    public function getHiddenBlockCount(): int
    {
        $grouped = $this->getGroupedBlocks();
        $total = $grouped['totalBlockCount'];

        return max(0, $total - self::MAX_VISIBLE_BLOCKS);
    }
}
