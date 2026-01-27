<?php

namespace Database\Factories;

use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\TaskSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskScheduleFactory extends Factory
{
    protected $model = TaskSchedule::class;

    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'user_id' => User::factory(),
            'ai_provider_id' => AiProvider::factory(),
            'name' => $this->faker->sentence(3),
            'prompt' => $this->faker->paragraph(),
            'cron_expression' => '0 * * * *',
            'builder_config' => null,
            'is_active' => true,
            'delete_after_minutes' => null,
        ];
    }
}
