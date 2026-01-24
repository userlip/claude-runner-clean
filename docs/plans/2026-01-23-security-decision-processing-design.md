# Security Decision Processing (Auto-merge + Deploy)

## Context
Security management tasks currently stop after the orchestrator responds. The system records the assistant message but does not parse the decision or progress the run (approve/merge/deploy), which leaves chats idle and runs stuck in `researching`.

## Goals
- Consume orchestrator decisions automatically.
- Update `security_runs` status and store decision summary.
- Merge Dependabot PRs when allowed and deploy via Ploi.
- Post status updates back into the security task chat.

## Non-goals
- Rewrite GitHub fetching or CI detection logic.
- Change provider routing (Codex orchestrator vs Claude fixer).

## Proposed Approach
1. Extend `SecurityManagementService` to process runs in `researching` after each repository scan.
2. Locate the matching orchestrator prompt by extracting the JSON payload (`pr_number`) from the latest user message in the security task.
3. Find the next assistant message after that prompt and parse the JSON decision block.
4. If `merge_allowed` is true:
   - Mark run `approved`, merge the PR via GitHub API, mark `merged` with commit sha.
   - Trigger Ploi deploy and mark `deployed`.
5. If `merge_allowed` is false:
   - Mark run `failed` and post the rationale.

## Data Flow
- Inputs: `security_runs` (status `researching`), security task messages.
- Processing: `processRepository` → `processResearchingRuns` → decision parsing.
- Outputs: `security_runs.decision_summary`, status updates, merge/deploy actions, chat messages.

## Error Handling
- If decision JSON is missing, the run remains `researching` and will retry on next schedule tick.
- Merge/deploy exceptions mark the run `failed` with `error_message` and post a failure message.

## Testing
- Feature test that seeds a `researching` run with a prompt + assistant decision and asserts:
  - status transitions to `deployed`.
  - merge commit sha stored.
  - deploy command executed (Process fake).

