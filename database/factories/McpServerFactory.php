<?php

namespace Database\Factories;

use App\Models\McpServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<McpServer>
 */
class McpServerFactory extends Factory
{
    protected $model = McpServer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'transport' => 'command',
            'command' => fake()->randomElement(['npx', 'node', 'bunx']),
            'args' => ['@modelcontextprotocol/server-filesystem', '/tmp'],
            'url' => null,
            'headers' => ['Authorization' => 'Bearer '.fake()->sha1()],
            'env_vars' => ['MCP_TOKEN' => fake()->sha1()],
            'enabled' => fake()->boolean(80),
            'last_tested_at' => null,
            'last_test_status' => null,
            'last_test_message' => null,
        ];
    }

    public function command(): static
    {
        return $this->state(fn (): array => [
            'transport' => 'command',
            'command' => 'npx',
            'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/tmp'],
            'url' => null,
            'headers' => [],
        ]);
    }

    public function sse(): static
    {
        return $this->state(fn (): array => [
            'transport' => 'sse',
            'command' => null,
            'args' => [],
            'url' => fake()->url(),
            'env_vars' => [],
        ]);
    }
}
