<?php

namespace App\Console\Commands;

use App\Enums\ResearchModule;
use App\Services\ResearchService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ResearchRunCommand extends Command
{
    protected $signature = 'research:run
                            {module? : The research module to run (api_opportunities, seo_keywords, etc.)}
                            {--all : Run all research modules}';

    protected $description = 'Run a research module to find opportunities and create proposals';

    public function handle(ResearchService $researchService, TelegramService $telegramService): int
    {
        if ($this->option('all')) {
            return $this->runAllModules($researchService, $telegramService);
        }

        $moduleName = $this->argument('module');

        if (! $moduleName) {
            $moduleName = $this->choice(
                'Which research module would you like to run?',
                array_map(fn ($m) => $m->value, ResearchModule::cases()),
                0
            );
        }

        $module = ResearchModule::tryFrom($moduleName);

        if (! $module) {
            $this->error("Invalid module: {$moduleName}");
            $this->info('Available modules: '.implode(', ', array_map(fn ($m) => $m->value, ResearchModule::cases())));

            return self::FAILURE;
        }

        return $this->runModule($module, $researchService, $telegramService);
    }

    protected function runModule(
        ResearchModule $module,
        ResearchService $researchService,
        TelegramService $telegramService
    ): int {
        $this->info("Starting research: {$module->label()}");

        try {
            $task = $researchService->createResearchTask($module);

            $this->info("Created research task: {$task->uuid}");
            $this->info('Task will be processed by z.ai');

            try {
                $telegramService->sendMessage(
                    "*Research Started*\n\n".
                    "*Module:* {$module->label()}\n".
                    "*Task ID:* `{$task->uuid}`\n\n".
                    'Research is now running...'
                );
            } catch (\Exception $e) {
                $this->warn("Could not send Telegram notification: {$e->getMessage()}");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to start research: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function runAllModules(
        ResearchService $researchService,
        TelegramService $telegramService
    ): int {
        $this->info('Running all research modules...');

        $results = [];
        foreach (ResearchModule::cases() as $module) {
            $result = $this->runModule($module, $researchService, $telegramService);
            $results[$module->value] = $result === self::SUCCESS;

            sleep(2);
        }

        $successful = count(array_filter($results));
        $total = count($results);

        $this->info("Completed: {$successful}/{$total} modules started successfully");

        return $successful === $total ? self::SUCCESS : self::FAILURE;
    }
}
