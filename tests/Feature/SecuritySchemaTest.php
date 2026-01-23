<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecuritySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_repositories_table_has_security_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('repositories', [
            'security_management_enabled',
            'ploi_server_id',
            'ploi_site_id',
            'security_task_id',
        ]));
    }

    public function test_security_runs_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('security_runs', [
            'id',
            'repository_id',
            'github_pr_id',
            'github_pr_number',
            'status',
            'decision_summary',
            'merge_commit_sha',
            'last_checked_at',
            'error_message',
            'created_at',
            'updated_at',
        ]));
    }
}
