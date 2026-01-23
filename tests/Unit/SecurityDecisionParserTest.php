<?php

namespace Tests\Unit;

use App\Services\SecurityDecisionParser;
use Tests\TestCase;

class SecurityDecisionParserTest extends TestCase
{
    public function test_parses_merge_allowed_from_json_block(): void
    {
        $content = "Here is decision:\n```json\n{\"merge_allowed\": true, \"risk_level\": \"low\"}\n```";
        $decision = (new SecurityDecisionParser)->parse($content);

        $this->assertTrue($decision['merge_allowed']);
        $this->assertSame('low', $decision['risk_level']);
    }
}
