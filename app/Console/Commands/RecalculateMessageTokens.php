<?php

namespace App\Console\Commands;

use App\Enums\MessageRole;
use App\Models\GeneralChatMessage;
use App\Models\Message;
use Illuminate\Console\Command;

class RecalculateMessageTokens extends Command
{
    protected $signature = 'messages:recalculate-tokens {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Recalculate token counts from raw_output for messages with incorrect values';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Recalculating token counts from raw_output...');
        if ($dryRun) {
            $this->warn('DRY RUN - no changes will be made');
        }

        // Process Task messages
        $this->processMessages(Message::class, 'Task messages');

        // Process GeneralChat messages
        $this->processMessages(GeneralChatMessage::class, 'General chat messages');

        $this->info('Done!');

        return Command::SUCCESS;
    }

    private function processMessages(string $modelClass, string $label): void
    {
        $dryRun = $this->option('dry-run');

        $messages = $modelClass::where('role', MessageRole::Assistant)
            ->whereNotNull('raw_output')
            ->whereNotNull('tokens_in')
            ->get();

        $this->info("Processing {$messages->count()} {$label}...");

        $fixed = 0;
        $skipped = 0;

        foreach ($messages as $message) {
            $calculated = $this->calculateTokensFromRawOutput($message->raw_output);

            if ($calculated === null) {
                $skipped++;

                continue;
            }

            // Check if the stored value differs significantly from calculated
            $storedIn = $message->tokens_in ?? 0;
            $storedOut = $message->tokens_out ?? 0;

            // If stored value is more than 2x the calculated, it's likely wrong
            if ($storedIn > 0 && $storedIn > $calculated['input_tokens'] * 2) {
                $this->line(sprintf(
                    '  Message #%d: tokens_in %s -> %s (was %sx higher)',
                    $message->id,
                    number_format($storedIn),
                    number_format($calculated['input_tokens']),
                    number_format($storedIn / max($calculated['input_tokens'], 1), 1)
                ));

                if (! $dryRun) {
                    $message->update([
                        'tokens_in' => $calculated['input_tokens'],
                        'tokens_out' => $calculated['output_tokens'],
                    ]);
                }

                $fixed++;
            }
        }

        $this->info("  Fixed: {$fixed}, Skipped: {$skipped}");
    }

    private function calculateTokensFromRawOutput(?string $rawOutput): ?array
    {
        if (empty($rawOutput)) {
            return null;
        }

        $lines = explode("\n", $rawOutput);
        $lastTurnUsage = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $data = json_decode($line, true);
            if (! $data) {
                continue;
            }

            if (($data['type'] ?? '') === 'assistant' && isset($data['message']['usage'])) {
                $usage = $data['message']['usage'];
                $inputTokens = ($usage['input_tokens'] ?? 0)
                    + ($usage['cache_read_input_tokens'] ?? 0)
                    + ($usage['cache_creation_input_tokens'] ?? 0);
                $outputTokens = $usage['output_tokens'] ?? 0;

                // Only update if we have actual token counts (not zero/empty)
                if ($inputTokens > 0 || $outputTokens > 0) {
                    $lastTurnUsage = [
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                    ];
                }
            }
        }

        return $lastTurnUsage;
    }
}
