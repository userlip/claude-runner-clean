# Ralph Wiggum Mode Design

> **Status:** Design Complete
> **Implementation Plan:** [2025-01-13-ralph-wiggum-mode-implementation.md](./2025-01-13-ralph-wiggum-mode-implementation.md)

## Overview

Ralph Wiggum mode transforms a single long-running AI session into iterative loops with fresh context. Each iteration starts with a new Claude session, reads state from files, executes work, and writes progress back to files. The chat context is discarded; only written state persists.

**Inspired by:** Geoffrey Huntley's Ralph Wiggum Loop technique

## Architecture

### Core Components

1. **Task Model Extensions** - New fields for Ralph configuration
2. **Workspace State Files** - `.ralph/` directory with state files
3. **RalphJob** - Loop job that handles iterations
4. **UI Integration** - Added to Task chat page

### Key Features

- **Token-based rotation** - Rotates at configurable threshold (default 70%)
- **Model rotation** - User-selected provider order for fallback
- **Guardrails system** - Learnings compound to prevent repeating mistakes
- **File-based state** - Memory persists via git + text files

## Database Schema

### Tasks Table Additions

| Column | Type | Description |
|--------|------|-------------|
| `ralph_enabled` | boolean | Whether Ralph mode is active |
| `ralph_iteration` | int | Current iteration number |
| `ralph_max_iterations` | int (nullable) | Hard limit (null = unlimited) |
| `ralph_anchor_path` | string (nullable) | Path to anchor file |
| `ralph_branch_name` | string (nullable) | Git branch for Ralph work |
| `ralph_rotation_threshold` | decimal | Token % for rotation (0.0-1.0, default 0.70) |
| `ralph_model_rotation` | json (nullable) | Ordered array of provider IDs |
| `ralph_last_rotation_at` | timestamp (nullable) | Last rotation time |
| `ralph_gutter_count` | int | Consecutive failures counter |
| `ralph_stopped_reason` | string (nullable) | Why Ralph stopped |

## Workspace State Files

Located in `{workspace_path}/.ralph/`:

```
.ralph/
├── prompt.md       # Agent instructions for each iteration
├── prd.json        # Product requirements with user stories
├── progress.txt    # Accumulated learnings and patterns
├── guardrails.md   # Learned constraints to prevent mistakes
└── activity.log    # Tool usage, tokens, timing per iteration
```

### prompt.md Template

```markdown
# Ralph Agent Instructions

## Your Task

1. Read `.ralph/prd.json`
2. Read `.ralph/progress.txt` (check Codebase Patterns first)
3. Check you're on the correct branch: `{{ $branchName }}`
4. Pick highest priority story where `passes: false`
5. Implement that ONE story
6. Run verification: `{{ $verificationCommand }}`
7. Commit if passing: `feat: [ID] - [Title]`
8. Update `.ralph/prd.json`: `passes: true`
9. Append learnings to `.ralph/progress.txt`

## Stop Condition

If ALL stories pass, reply: <promise>COMPLETE</promise>
```

### prd.json Structure

```json
{
  "branchName": "ralph/{{ task.uuid }}",
  "verificationCommand": "php artisan test",
  "userStories": [
    {
      "id": "US-001",
      "title": "Add login form",
      "acceptanceCriteria": ["Email field", "Password field", "Tests pass"],
      "priority": 1,
      "passes": false
    }
  ]
}
```

## The Loop (RunRalphJob)

```php
class RunRalphJob implements ShouldQueue
{
    public function handle(): void
    {
        // 1. Check rotation threshold
        if ($this->shouldRotate()) {
            $this->rotateContext();
        }

        // 2. Read state files
        $prd = json_decode($this->read('.ralph/prd.json'), true);

        // 3. Check completion
        if ($this->allStoriesPassed($prd)) {
            $this->task->update(['status' => TaskStatus::Completed]);
            return;
        }

        // 4. Pick next story
        $story = $this->pickNextStory($prd);

        // 5. Execute Claude with fresh context
        $result = $this->executeClaude($this->buildPrompt($story));

        // 6. Run verification
        if ($this->runVerification($result->commandOutput)) {
            $this->commitChanges($story);
            $this->markStoryPassed($prd, $story);
            $this->appendLearnings($result->learnings);
        }

        // 7. Dispatch next iteration
        self::dispatch($this->task, $this->iteration + 1);
    }
}
```

## UI Integration

### Task Chat Page Additions

1. **Ralph Mode Toggle** - Enable/disable with settings
2. **Configuration Modal** - Set branch, models, stories
3. **Real-time Status Panel** - Iteration, tokens, stories progress
4. **Progress.txt Viewer** - View accumulated learnings
5. **PRD Editor** - Manage user stories inline

### Status Panel Display

```
┌─ Ralph Loop Status ────────────────────┐
│ Iteration: 7 / 25                      │
│ Tokens: 68,432 / 200,000 (34%)        │
│ Stories: 3/5 passed                    │
│ Last: "Add login form" ✓ passed        │
│ Next: "Add email validation"           │
│                                         │
│ [Pause] [View Logs] [Force Rotation]   │
└─────────────────────────────────────────┘
```

## Guardrails System

### Guardrail Format

```markdown
### sign: check imports before adding
- trigger: adding a new import statement
- instruction: check if import already exists
- added after: iteration 3 (duplicate import broke build)
```

### Gutter Detection

When same error repeats 3+ times:
1. Increment `ralph_gutter_count`
2. Notify user of gutter state
3. Request guardrail addition
4. Pause loop for intervention

## Model Rotation

### Configuration

User selects ordered list of AI providers:
1. Primary (default: current task's provider)
2. Secondary fallbacks

### Rotation Logic

```php
$providers = $task->ralph_model_rotation; // [1, 5, 3]
$index = $task->ralph_iteration % count($providers);
$nextProvider = AiProvider::find($providers[$index]);
```

## Error Handling

| Scenario | Action |
|----------|--------|
| Verification fails | Don't commit, don't mark passed, log error |
| Max iterations reached | Mark task failed, notify user |
| Gutter detected | Pause, request guardrail |
| Token threshold | Rotate context, optional model rotation |

## Testing Strategy

### Unit Tests
- Rotation detection logic
- Story selection (priority + pass status)
- Gutter detection (repeated errors)

### Feature Tests
- End-to-end Ralph flow
- Model rotation
- Workspace initialization

### Livewire Tests
- Ralph toggle enable/disable
- Configuration validation
- Real-time status updates

## Implementation Order

1. Database migration
2. Model updates (Task)
3. RalphWorkspaceService
4. RunRalphJob
5. UI components (Livewire)
6. Testing

## References

- [Original Ralph post by Geoffrey Huntley](https://x.com/GeoffreyHuntley/status/1875139829050491264)
- [Step-by-step guide by Ryan Carson](https://x.com/ryancarson/status/1876463666998808656)
- [Cursor port by Agrim Singh](https://github.com/agrimsingh/ralph-wiggum-cursor)
