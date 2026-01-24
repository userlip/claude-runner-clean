# User Needed Actions for Major Dependabot Updates

## Context
Security automation currently handles dependabot PRs by waiting for CI and applying an LLM decision. For repos without CI, majors should not auto‑merge; they should be escalated to the user with researched breaking changes.

## Goal
- Auto‑merge patch/minor updates when the decision says safe, even if no CI exists.
- For major updates, **never auto‑merge or close**. Create a user‑facing action with breaking‑changes research.

## Approach
1. Extend the decision processing step to extract update type (major/minor/patch) from Dependabot PR title/body.
2. If update type is **major**, create a `Proposal` entry labeled **User needed actions** containing:
   - dependency name
   - current → target versions
   - breaking‑changes summary from orchestrator decision
   - available options (merge / ignore major / leave open)
3. Mark the security run as `needs_user_action` to prevent repeat processing.
4. Keep patch/minor flow unchanged: merge if the decision allows.

## Prompt Adjustments
Update the orchestrator prompt to:
- not block on CI when checks are absent
- explicitly research breaking changes for major updates
- include `breaking_changes` in the JSON decision

## Error Handling
- If update type cannot be determined, fall back to existing decision logic.
- If a proposal already exists for the PR, do not create duplicates.

## Testing
- Added a feature test to assert major updates create a pending proposal and set `needs_user_action`.
- Existing security‑management tests still pass.
