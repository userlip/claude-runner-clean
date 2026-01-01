<?php

namespace App\Console\Commands;

use App\Services\PloiService;
use Illuminate\Console\Command;

class SetupReverbDaemon extends Command
{
    protected $signature = 'reverb:setup-daemon
                            {--restart : Restart existing daemon instead of creating}
                            {--delete : Delete the Reverb daemon}';

    protected $description = 'Set up Laravel Reverb as a Ploi daemon';

    public function handle(PloiService $ploi): int
    {
        $directory = base_path();
        $command = 'php artisan reverb:start';

        // Check if daemon already exists
        $existing = $ploi->findDaemonByCommand('reverb:start');

        if ($this->option('delete')) {
            if (! $existing) {
                $this->warn('No Reverb daemon found to delete.');

                return self::SUCCESS;
            }

            $ploi->deleteDaemon($existing['id']);
            $this->info('Reverb daemon deleted successfully.');

            return self::SUCCESS;
        }

        if ($this->option('restart')) {
            if (! $existing) {
                $this->warn('No Reverb daemon found to restart.');

                return self::FAILURE;
            }

            $ploi->restartDaemon($existing['id']);
            $this->info('Reverb daemon restarted successfully.');

            return self::SUCCESS;
        }

        if ($existing) {
            $this->info('Reverb daemon already exists (ID: '.$existing['id'].')');
            $this->line('Status: '.$existing['status']);

            if ($this->confirm('Would you like to restart it?')) {
                $ploi->restartDaemon($existing['id']);
                $this->info('Daemon restarted.');
            }

            return self::SUCCESS;
        }

        // Create new daemon
        $this->info('Creating Reverb daemon via Ploi...');

        try {
            $ploi->createDaemon($command, $directory, 'ploi', 1);
            $this->info('Reverb daemon created successfully!');
            $this->newLine();
            $this->line('The daemon will start automatically and restart on server reboot.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to create daemon: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
