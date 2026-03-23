<?php

namespace Tests;

use App\Events\RecentChatsUpdated;
use App\Events\TaskChatUpdated;
use App\Events\TaskStatusUpdated;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Prevent actual broadcast attempts during tests.
        // Channel authorization is still tested in BroadcastChannelAuthTest.
        Event::fake([RecentChatsUpdated::class, TaskStatusUpdated::class, TaskChatUpdated::class]);
    }
}
