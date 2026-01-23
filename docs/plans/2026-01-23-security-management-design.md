# Security Management Automation Design

**Goal:** Automatically review and merge Dependabot PRs for selected repositories, using AI + Scrappa MCP research, gated by GitHub CI status, and deploy via Ploi CLI. Provide a long-lived task/chat per repo with clear status updates and manual trigger controls.

## Architecture Overview
- **Scheduler:** Laravel scheduler runs every minute and triggers a command (`security:orchestrate`).
- **Orchestrator Job:** Enqueued job processes enabled repositories, ensures a single “Security Management” task per repo, and posts status updates.
- **GitHub Integration:** Uses existing GitHub token to list Dependabot PRs, check CI status, fetch metadata, and merge when safe.
- **AI Research:** For each PR with green CI, the orchestrator posts a prompt into the repo’s security task. The AI must use Scrappa MCP to fetch release notes and advisories, then output a structured decision.
- **Deployment:** After merge, the job resolves Ploi server/site IDs (using stored IDs or Ploi CLI lookup) and deploys the repo’s site.
- **Idempotency:** A `security_runs` table tracks PR status transitions and prevents double-merge/deploy.

## Data Model
### repositories (new fields)
- `security_management_enabled` (bool, default false)
- `ploi_server_id` (string/int, nullable)
- `ploi_site_id` (string/int, nullable)
- `security_task_id` (foreign key to `tasks`, nullable)

### security_runs (new table)
- `repository_id` (FK)
- `github_pr_id` (bigint)
- `github_pr_number` (int)
- `status` (enum: pending, waiting_ci, researching, approved, merged, deployed, failed)
- `decision_summary` (text, nullable)
- `merge_commit_sha` (string, nullable)
- `last_checked_at` (timestamp)
- `error_message` (text, nullable)
- Unique index on (`repository_id`, `github_pr_id`).

## UI / UX
- **Repositories list:** toggle for “Security Mgmt.”
- **Repository edit/view:** show/modify `ploi_server_id` + `ploi_site_id`, link to “Security Management” task, and add a “Run Security Check” action.
- **Task chat:** long-lived task per repo where the orchestrator posts progress and AI decisions.

## Orchestrator State Machine
1. **pending** → query CI status
2. **waiting_ci** → recheck until green
3. **researching** → run AI prompt + Scrappa MCP
4. **approved** → merge PR
5. **merged** → deploy via Ploi CLI
6. **deployed** → done
7. **failed** → retry next tick (with max retries + throttle)

## AI Prompt + Scrappa MCP
Prompt stored at `resources/prompts/security/dependabot.md`.
- Inputs: repo metadata, PR metadata, CI summary, diff stats, and links.
- Required actions: use Scrappa MCP to fetch release notes + security advisories.
- Output: structured decision with `merge_allowed` boolean and cited sources.
- Safety: never merge on failing CI; fail closed if data is inconclusive.

## AI Provider Selection (Global)
- Global config for **orchestrator** and **fixer** providers.
- Orchestrator task uses the orchestrator provider.
- Any remediation task (if created) uses the fixer provider.
- Fallback: `AiProvider::getDefault()` if config is unset.

## Failure Handling
- Rate limits: exponential backoff and task notice.
- CI pending: status message only (no merge).
- AI uncertainty: mark `failed` / `pending_review`, request manual review.
- Deploy failures: log + retry; no automatic rollback initially.

## Manual Trigger
- Filament action + CLI command allow on-demand runs per repo.

## Testing Strategy
- Unit tests for state machine transitions and GitHub API fakes.
- Feature tests for Filament toggle/action.
- Ploi CLI resolution logic covered with process fakes.

