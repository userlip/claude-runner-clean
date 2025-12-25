<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RegularUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::createOrFirst([
            'name' => 'User',
            'guard_name' => 'web',
        ]);

        $users = [
            [
                'name' => fake()->name(),
                'email' => fake()->email(),
                'password' => 'password',
            ],
        ];

        foreach ($users as $user) {
            $user = User::create($user);
            $user->assignRole($role);
        }
    }
}
