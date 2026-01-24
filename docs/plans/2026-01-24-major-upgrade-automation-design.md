# Major Upgrade Automation Design

Date: 2026-01-24

## Summary

When a Dependabot PR is classified as a **major** version update in a repository with `security_management_enabled = true`, the system will automatically spawn a **Major Upgrade task** that upgrades, fixes, tests, and produces a PR plus a **temporary review site** on the same Ploi server as production. The review site uses production `.env` with **read-only DB credentials** and an updated `APP_URL` so data remains safe.

## Goals

- Automatically handle major upgrades with structured automation instead of manual triage.
- Produce a clean PR and a review site for quick validation.
- Use read-only DB credentials to prevent accidental writes to prod data.
- Keep visibility and auditability in Claude Runner tasks/chats and proposals.

## Non-Goals

- Automatically merge major upgrades into production without review.
- Change existing Dependabot or CI behavior outside the major-upgrade path.

## Architecture & Flow

1) **Detect major update**
   - Security Management classifies Dependabot updates by semver.
   - For major updates, we **create a Major Upgrade task** and keep a “User needed actions” proposal as an audit trail.

2) **Orchestrator (Codex)**
   - Uses `gh` CLI to fetch PR details and release notes.
   - Checks out the Dependabot branch.
   - Runs dependency installation and upgrade steps.
   - Runs tests/builds and captures failures.

3) **Fixer (Claude)**
   - Receives failures/errors from orchestrator.
   - Applies code/config fixes until tests/build succeed.

4) **PR Creation**
   - Creates branch `major-upgrade/pr-<num>`.
   - Opens PR with summary + test output + release-notes deltas.

5) **Review Site**
   - Create a temporary Ploi site on **same server** as production.
   - Deploy the major-upgrade branch.
   - Set `APP_URL` and `SESSION_DOMAIN` to review domain.
   - Use **read-only DB credentials**.

## Data Model

### MajorUpgradeRun (new model/table)

Fields:
- `repository_id`
- `github_pr_number`
- `status` (enum)
- `source_pr_url`
- `source_pr_sha`
- `work_branch`
- `upgrade_summary`
- `error_message`
- `review_site_url`
- `review_site_id`
- `created_by_task_id`
- `last_checked_at`

Statuses:
- `pending`, `researching`, `upgrading`, `fixing`, `testing`, `pr_opened`, `review_site_created`, `completed`, `failed`

## Ploi Review Site Provisioning

1) Resolve production Ploi IDs for the repo if missing.
2) Create review site using a deterministic domain:
   - `review-<repo>-pr<num>.marin.sh`
3) Pull production `.env` with `ploi env:pull`.
4) Create read-only DB user via Ploi CLI:
   - `ploi database:create-user --server=<id> --database=<db> --user=<name> --password=<pw> --readonly`
5) Generate review `.env`:
   - Replace `APP_URL`, `SESSION_DOMAIN`.
   - Replace `DB_USERNAME`, `DB_PASSWORD` with read-only user.
   - Optional: `APP_ENV=review`, `LOG_CHANNEL=stack`.
6) Push `.env` to review site and deploy branch.
7) Perform a basic HTTP health check and record result.

## Safety & Error Handling

- If read-only DB user creation fails, mark run **failed** and create a user action proposal.
- If review-site provisioning fails, keep PR open and note failure in task chat.
- All failures are logged to the task messages and stored on the run record.

## Prompts

### Orchestrator prompt
- Use `gh` CLI to fetch PR metadata.
- Pull release notes and identify breaking changes.
- Attempt upgrade, run checks, and delegate fixes.

### Fixer prompt
- Fix compilation/test errors.
- Update config/scripts as needed.
- Ensure tests pass.

## UI/UX

- New “Major Upgrades” section in admin:
  - List of runs with status, repo, PR, review site URL, and outcome.
- Link each run to its task chat and PR.
- Keep “User needed actions” proposal for audit/approval.

## Testing

- Unit tests for semver detection and MajorUpgradeRun creation.
- Feature test ensuring a major update triggers the new run/task.
- Integration test for building review env and ensuring `DB_USERNAME` is replaced.

## Open Questions

- Cleanup policy for review sites (automatic delete after N days?).
- Whether to auto-request SSL cert for review sites.

