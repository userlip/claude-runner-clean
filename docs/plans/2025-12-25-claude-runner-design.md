# Claude Runner - Design Document

## Overview

A Laravel/Filament dashboard to invoke, monitor, and control Claude Code instances. Users sync GitHub repositories, provision sites via Ploi CLI, and run Claude Code tasks in a chat-style interface.

## Architecture

```
┌─────────────────────────────────────────┐
│       Filament Dashboard (UI)           │
│  - GitHub OAuth & repo sync             │
│  - Site provisioning via Ploi CLI       │
│  - Chat-style task interface            │
│  - Parsed Claude output rendering       │
└──────────────────┬──────────────────────┘
                   │ AJAX polling
┌──────────────────▼──────────────────────┐
│        Laravel Queue Job                 │
│  - Run Claude Code process              │
│  - Stream output to database            │
│  - Update task/message status           │
└──────────────────┬──────────────────────┘
                   │ proc_open()
┌──────────────────▼──────────────────────┐
│         Claude Code CLI                  │
│  - claude -p "prompt"                   │
│  - --output-format stream-json          │
│  - --session-id <uuid>                  │
└─────────────────────────────────────────┘
```

## Data Model

### `github_connections`

Single record per user storing OAuth credentials.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| user_id | foreignId | Owner |
| access_token | text (encrypted) | GitHub OAuth token |
| github_user_id | string | GitHub user ID |
| github_username | string | Display name |
| scopes | json | Granted scopes |
| created_at | timestamp | |
| updated_at | timestamp | |

### `repositories`

Synced GitHub repo metadata (no local clone).

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| user_id | foreignId | Owner |
| github_id | bigint | GitHub's repo ID |
| name | string | Repo name |
| full_name | string | owner/repo |
| clone_url | string | HTTPS clone URL |
| ssh_url | string | SSH clone URL |
| default_branch | string | e.g., main |
| private | boolean | |
| description | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### `sites`

Provisioned sites via Ploi CLI.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| repository_id | foreignId | Parent repo |
| domain | string | e.g., feature-auth.marin.sh |
| path | string nullable | Set after provisioning, e.g., /home/ploi/feature-auth.marin.sh |
| ploi_site_id | string nullable | Ploi's site ID |
| php_version | string | 8.1, 8.2, 8.3, 8.4 |
| web_directory | string | Default: /public |
| isolated_user | boolean | Ploi user isolation |
| database_name | string nullable | If database created |
| deploy_script | text nullable | Custom deploy commands |
| status | enum | pending, provisioning, active, failed |
| error_message | text nullable | If failed |
| created_at | timestamp | |
| updated_at | timestamp | |

### `tasks`

A Claude Code conversation/session.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| uuid | uuid | Public identifier |
| site_id | foreignId | Parent site |
| session_id | uuid | Claude Code session ID |
| status | enum | pending, running, completed, failed |
| max_turns | int nullable | Optional turn limit |
| started_at | timestamp nullable | |
| completed_at | timestamp nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### `messages`

Individual messages in a task conversation.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| task_id | foreignId | Parent task |
| role | enum | user, assistant |
| content | text | User prompt or parsed assistant text |
| raw_output | longtext nullable | JSON stream (assistant only) |
| tool_calls | json nullable | Parsed tool uses |
| tokens_in | int nullable | Input tokens |
| tokens_out | int nullable | Output tokens |
| cost_usd | decimal nullable | Cost in USD |
| created_at | timestamp | |
| updated_at | timestamp | |

## GitHub OAuth Flow

1. User clicks "Connect GitHub" in settings
2. Redirect to GitHub OAuth with `repo` scope
3. GitHub redirects back with authorization code
4. Exchange code for access token
5. Store encrypted token in `github_connections`
6. Immediately sync repository list
7. "Refresh repos" button for on-demand re-sync

## Site Provisioning via Ploi CLI

### Creation Form

| Field | Type | Notes |
|-------|------|-------|
| Domain | text | Single subdomain only (e.g., feature-auth.marin.sh) |
| PHP Version | select | 8.1, 8.2, 8.3, 8.4 |
| Web Directory | text | Default: /public |
| Create Database | toggle | |
| Database Name | text | Auto-suggested from domain |
| Isolated User | toggle | |
| Run Composer Install | toggle | |
| Deploy Script | textarea | Optional |

### Provisioning Flow

1. User submits form from Repository action
2. Site record created with `status: provisioning`
3. `ProvisionSiteJob` dispatched to queue
4. Job executes Ploi CLI commands:
   - Create site with specified options
   - Attach repository
   - Trigger initial deploy
5. On success: update `path`, `ploi_site_id`, `status: active`
6. On failure: set `status: failed`, store `error_message`

## Task Execution

### Starting a Task

1. User opens Site chat interface
2. Types prompt, submits
3. Task created with `session_id: uuid()`
4. Message created with `role: user`
5. `RunClaudeMessageJob` dispatched

### Claude Code Invocation

```bash
claude -p "<prompt>" \
  --output-format stream-json \
  --session-id <task.session_id> \
  --max-turns <if set>
```

Working directory: Site's `path`

### Streaming Output

- Job uses `proc_open()` to run Claude
- Reads stdout line-by-line (JSON events)
- Appends to Message `raw_output`
- Parses events, updates `tool_calls`, `content`
- Extracts token usage on completion

### Continuing Conversation

- User types follow-up in same chat
- New Message with `role: user`
- Job runs with `--continue` flag and same `--session-id`
- New assistant Message appended to thread

## Polling API

`GET /api/tasks/{uuid}/messages?since={message_id}`

- Returns messages created after the given ID
- Frontend polls every 2s while task is running
- Stops polling when task status is completed/failed

## UI Rendering

### Event Type Mapping

| Event Type | UI Component |
|------------|--------------|
| assistant | Chat bubble with markdown |
| user | User prompt bubble |
| tool_use | Collapsible card with tool name + params |
| tool_result | Result under tool card |
| result | Summary + token badge |
| error | Red error banner |

### Tool-Specific Display

| Tool | Rendering |
|------|-----------|
| Read | "Read `path`" with expandable content |
| Edit | Diff view (old → new) |
| Write | "Created `path`" with syntax highlighting |
| Bash | Command + collapsible output |
| Glob/Grep | File/line list |
| TodoWrite | Todo list UI |

## Filament Structure

### Resources

| Resource | Purpose |
|----------|---------|
| RepositoryResource | List synced repos (read-only). Action: Create Site |
| SiteResource | List/create/view sites |
| TaskResource | Minimal - tasks viewed via chat UI |

### Pages

| Page | Route |
|------|-------|
| GitHubSettings | /admin/settings/github |
| SiteChat | /admin/sites/{site}/chat |

### Navigation

```
├── Dashboard
├── Repositories
├── Sites
│   └── Chat (per site)
└── Settings
    └── GitHub Connection
```

### Dashboard Widgets

- Recent tasks across all sites
- Sites by status

## Security

- GitHub tokens encrypted with Laravel's `encrypted` cast
- Site paths validated within `/home/ploi/`
- Prompts sanitized with `escapeshellarg()`
- Rate limiting on polling endpoints
- Task execution inherits `ploi` user permissions

## Process Management

- Queue timeout: 10+ minutes for long-running tasks
- Failed processes marked as `failed` status
- Cancel action sends SIGTERM
- Stale tasks (no output 5min) can be force-killed

## Edge Cases

- GitHub token expiry: prompt reconnect, existing sites unaffected
- Site provisioning failure: clear error, allow retry
- Claude session expiry: start new session transparently
- Large output: paginate content, lazy-load raw JSON
