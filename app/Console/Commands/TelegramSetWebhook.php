<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook
                            {--url= : The webhook URL (will be generated if not provided)}
                            {--delete : Delete the webhook instead of setting it}
                            {--info : Show current webhook info}
                            {--generate-secret : Generate a new webhook secret}';

    protected $description = 'Set up the Telegram bot webhook';

    public function handle(TelegramService $telegram): int
    {
        if ($this->option('info')) {
            return $this->showWebhookInfo($telegram);
        }

        if ($this->option('generate-secret')) {
            return $this->generateSecret();
        }

        if ($this->option('delete')) {
            return $this->deleteWebhook($telegram);
        }

        return $this->setWebhook($telegram);
    }

    private function setWebhook(TelegramService $telegram): int
    {
        $secret = config('telegram.webhook_secret');

        if (empty($secret)) {
            $this->error('TELEGRAM_WEBHOOK_SECRET is not set in .env');
            $this->info('Run: php artisan telegram:set-webhook --generate-secret');

            return Command::FAILURE;
        }

        $url = $this->option('url');

        if (empty($url)) {
            $baseUrl = config('app.url');
            $url = "{$baseUrl}/api/telegram/webhook/{$secret}";
        }

        $this->info("Setting webhook to: {$url}");

        if ($telegram->setWebhook($url)) {
            $this->info('Webhook set successfully!');
            $this->showWebhookInfo($telegram);

            return Command::SUCCESS;
        }

        $this->error('Failed to set webhook. Check the logs for details.');

        return Command::FAILURE;
    }

    private function deleteWebhook(TelegramService $telegram): int
    {
        $this->info('Deleting webhook...');

        if ($telegram->deleteWebhook()) {
            $this->info('Webhook deleted successfully!');

            return Command::SUCCESS;
        }

        $this->error('Failed to delete webhook. Check the logs for details.');

        return Command::FAILURE;
    }

    private function showWebhookInfo(TelegramService $telegram): int
    {
        $this->info('Current webhook info:');

        $info = $telegram->getWebhookInfo();

        if (empty($info)) {
            $this->warn('Could not retrieve webhook info.');

            return Command::FAILURE;
        }

        $this->table(
            ['Property', 'Value'],
            [
                ['URL', $info['url'] ?? 'Not set'],
                ['Has Custom Certificate', ($info['has_custom_certificate'] ?? false) ? 'Yes' : 'No'],
                ['Pending Update Count', $info['pending_update_count'] ?? 0],
                ['Last Error Date', isset($info['last_error_date']) ? date('Y-m-d H:i:s', $info['last_error_date']) : 'None'],
                ['Last Error Message', $info['last_error_message'] ?? 'None'],
                ['Max Connections', $info['max_connections'] ?? 40],
                ['Allowed Updates', isset($info['allowed_updates']) ? implode(', ', $info['allowed_updates']) : 'All'],
            ]
        );

        return Command::SUCCESS;
    }

    private function generateSecret(): int
    {
        $secret = Str::random(64);

        $this->info('Generated webhook secret:');
        $this->newLine();
        $this->line("TELEGRAM_WEBHOOK_SECRET={$secret}");
        $this->newLine();
        $this->info('Add this to your .env file, then run:');
        $this->line('php artisan telegram:set-webhook');

        return Command::SUCCESS;
    }
}
