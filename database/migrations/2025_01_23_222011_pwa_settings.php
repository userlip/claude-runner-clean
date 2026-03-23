<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'pwa_app_name' => config('app.name'),
            'pwa_short_name' => config('app.name'),
            'pwa_start_url' => '/',
            'pwa_background_color' => '#ffffff',
            'pwa_theme_color' => '#000000',
            'pwa_display' => 'standalone',
            'pwa_orientation' => 'any',
            'pwa_status_bar' => '#000000',
            'pwa_icons_72x72' => '',
            'pwa_icons_96x96' => '',
            'pwa_icons_128x128' => '',
            'pwa_icons_144x144' => '',
            'pwa_icons_152x152' => '',
            'pwa_icons_192x192' => '',
            'pwa_icons_384x384' => '',
            'pwa_icons_512x512' => '',
            'pwa_splash_640x1136' => '',
            'pwa_splash_750x1334' => '',
            'pwa_splash_828x1792' => '',
            'pwa_splash_1125x2436' => '',
            'pwa_splash_1242x2208' => '',
            'pwa_splash_1242x2688' => '',
            'pwa_splash_1536x2048' => '',
            'pwa_splash_1668x2224' => '',
            'pwa_splash_1668x2388' => '',
            'pwa_splash_2048x2732' => '',
            'pwa_shortcuts' => [],
        ];

        foreach ($settings as $name => $value) {
            DB::table('settings')->insertOrIgnore([
                'group' => 'pwa',
                'name' => $name,
                'locked' => false,
                'payload' => json_encode($value),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'pwa')->delete();
    }
};
