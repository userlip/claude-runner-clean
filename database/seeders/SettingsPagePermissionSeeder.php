<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SettingsPagePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRoles = Role::whereIn('name', [
            'Super Admin',
            'Admin',
        ])->get();

        $settingsHubPermission = Permission::createOrFirst([
            'name' => 'page_SettingsHub',
            'guard_name' => 'web',
        ]);

        $siteSettingsPermission = Permission::createOrFirst([
            'name' => 'page_SiteSettings',
            'guard_name' => 'web',
        ]);

        $pwaPermission = Permission::createOrFirst([
            'name' => 'page_PWASettingsPage',
            'guard_name' => 'web',
        ]);

        $socialLinksPermissions = Permission::createOrFirst([
            'name' => 'page_SocialMenuSettings',
            'guard_name' => 'web',
        ]);

        $locationSettingsPermission = Permission::createOrFirst([
            'name' => 'page_LocationSettings',
            'guard_name' => 'web',
        ]);

        $settingsHubPermission->roles()->attach($adminRoles);
        $siteSettingsPermission->roles()->attach($adminRoles);
        $pwaPermission->roles()->attach($adminRoles);
        $socialLinksPermissions->roles()->attach($adminRoles);
        $locationSettingsPermission->roles()->attach($adminRoles);
    }
}
