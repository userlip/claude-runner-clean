<?php

namespace Database\Factories;

use App\Enums\SiteStatus;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        $subdomain = fake()->unique()->slug(1);

        return [
            'repository_id' => Repository::factory(),
            'domain' => "{$subdomain}.marin.sh",
            'path' => null,
            'ploi_site_id' => null,
            'php_version' => '8.4',
            'web_directory' => '/public',
            'isolated_user' => false,
            'database_name' => null,
            'deploy_script' => null,
            'status' => SiteStatus::Pending,
            'error_message' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => SiteStatus::Active,
            'path' => '/home/ploi/'.fake()->slug(1).'.marin.sh',
            'ploi_site_id' => (string) fake()->randomNumber(6),
        ]);
    }

    public function provisioning(): static
    {
        return $this->state(['status' => SiteStatus::Provisioning]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => SiteStatus::Failed,
            'error_message' => 'Provisioning failed: timeout',
        ]);
    }

    public function withDatabase(): static
    {
        return $this->state(fn () => [
            'database_name' => 'db_'.fake()->slug(1),
        ]);
    }
}
