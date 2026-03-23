<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $startUrl = $this->readSetting('pwa', 'pwa_start_url');
        if ($startUrl === null || $startUrl === '' || $startUrl === '/') {
            $this->writeSetting('pwa', 'pwa_start_url', '/admin');
        }

        $statusBar = $this->readSetting('pwa', 'pwa_status_bar');
        if (! in_array($statusBar, ['default', 'black', 'black-translucent'], true)) {
            $this->writeSetting('pwa', 'pwa_status_bar', 'default');
        }
    }

    private function readSetting(string $group, string $name): mixed
    {
        $payload = DB::table('settings')
            ->where('group', $group)
            ->where('name', $name)
            ->value('payload');

        if ($payload === null) {
            return null;
        }

        return json_decode($payload, true);
    }

    private function writeSetting(string $group, string $name, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => $group, 'name' => $name],
            [
                'payload' => json_encode($value),
                'locked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
