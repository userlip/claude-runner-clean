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

    public function test_ignores_ci_fixer_json_and_finds_orchestrator_decision(): void
    {
        $content = <<<'CONTENT'
Here is the CI fixer result:

```json
{"ci_fixed": false, "actions_taken": ["tried something"], "remaining_issues": ["still failing"], "needs_manual_intervention": false}
```

And now my orchestrator decision:

```json
{"merge_allowed": true, "action": "merge", "risk_level": "low", "rationale": "CI passed"}
```
CONTENT;

        $decision = (new SecurityDecisionParser)->parse($content);

        $this->assertNotNull($decision);
        $this->assertTrue($decision['merge_allowed']);
        $this->assertSame('merge', $decision['action']);
        $this->assertSame('low', $decision['risk_level']);
        $this->assertArrayNotHasKey('ci_fixed', $decision);
    }

    public function test_returns_null_when_no_orchestrator_decision(): void
    {
        $content = <<<'CONTENT'
Here is just a CI fixer response:

```json
{"ci_fixed": true, "actions_taken": ["fixed something"]}
```
CONTENT;

        $decision = (new SecurityDecisionParser)->parse($content);

        $this->assertNull($decision);
    }

    public function test_parses_ci_fixer_response(): void
    {
        $content = <<<'CONTENT'
Here is the CI fixer result:

```json
{"ci_fixed": true, "actions_taken": ["updated workflow"], "remaining_issues": [], "needs_manual_intervention": false}
```
CONTENT;

        $response = (new SecurityDecisionParser)->parseCiFixerResponse($content);

        $this->assertNotNull($response);
        $this->assertTrue($response['ci_fixed']);
        $this->assertSame(['updated workflow'], $response['actions_taken']);
    }

    public function test_parses_ci_fixer_response_ignores_orchestrator_decision(): void
    {
        $content = <<<'CONTENT'
```json
{"merge_allowed": true, "action": "merge", "risk_level": "low"}
```

```json
{"ci_fixed": false, "actions_taken": [], "needs_manual_intervention": true}
```
CONTENT;

        $response = (new SecurityDecisionParser)->parseCiFixerResponse($content);

        $this->assertNotNull($response);
        $this->assertFalse($response['ci_fixed']);
        $this->assertTrue($response['needs_manual_intervention']);
        $this->assertArrayNotHasKey('merge_allowed', $response);
    }
}
