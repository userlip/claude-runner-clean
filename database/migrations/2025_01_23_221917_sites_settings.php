<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'site_name' => '3x1',
            'site_description' => 'Creative Solutions',
            'site_keywords' => 'Graphics, Marketing, Programming',
            'site_profile' => '',
            'site_logo' => '',
            'site_author' => 'Fady Mondy',
            'site_address' => 'Cairo, Egypt',
            'site_email' => 'info@3x1.io',
            'site_phone' => '+201207860084',
            'site_phone_code' => '+2',
            'site_location' => 'Egypt',
            'site_currency' => 'EGP',
            'site_language' => 'English',
            'site_social' => [],
        ];

        foreach ($settings as $name => $value) {
            DB::table('settings')->insertOrIgnore([
                'group' => 'sites',
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
        DB::table('settings')->where('group', 'sites')->delete();
    }
};
