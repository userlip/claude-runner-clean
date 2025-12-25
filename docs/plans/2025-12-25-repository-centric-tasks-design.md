# Repository-Centric Tasks Design

## Overview

Restructure Claude Runner so tasks belong directly to repositories, not sites. Sites become optional deployment targets created from completed work.

## Current vs New Model

**Current (wrong):**
```
Repository → Site → Task → Message
```
Sites are prerequisites for running Claude Code tasks.

**New:**
```
Repository ←→ Site (optional link)
     ↓
   Task → Message
     ↓
  Workspace (cloned code)
```
Tasks run on repositories. Sites are optional deployment targets.

## Data Model Changes

### Task Model
- Remove required `site_id` foreign key
- Add `repository_id` (required) - which repo this task works on
- Add `workspace_path` (nullable) - path to cloned workspace
- Add `site_id` (nullable) - if working on existing site instead of workspace

### Site Model
- Make `repository_id` nullable - synced sites may not match a repo
- Add `branch` (string, nullable) - which branch the site deploys from
- Add `synced_from_ploi` (boolean, default false) - true if imported from Ploi

### Repository Model
No schema changes. Workspace path is computed: `/home/ploi/workspaces/{repo-name}-{task-uuid}`

## Ploi Site Syncing

### PloiService
New service that uses Ploi CLI to fetch and sync sites:
- Runs `ploi site:list --server={configured-server}`
- Parses: ID, domain, PHP version, project type, repository info
- Creates/updates Site records with `synced_from_ploi = true`

### Auto-matching
When syncing sites:
- If Ploi site has a GitHub repo attached, match by `clone_url` or `full_name`
- Matched sites get `repository_id` set
- Unmatched sites have `repository_id = null`

### Path Detection
- Standard sites: `/home/ploi/{domain}`
- Isolated user sites: `/home/{user}/{domain}`
- Store in `path` field for direct access

### UI
"Sync Sites" toolbar action on Sites resource (similar to "Sync Repositories").

## Task Creation Flow

### Step 1: Select Repository
- Searchable dropdown of synced repositories
- Shows: repo name, description, last synced

### Step 2: Choose Work Location
After selecting repo:
- **"New workspace"** - Always available. Fresh clone to `/home/ploi/workspaces/{repo-name}-{task-uuid}`
- **"Site: domain.com"** - Only if repo has linked site(s). Work on live code.

### Step 3: Start Task
- Creates Task with `repository_id` + either `workspace_path` or `site_id`
- If workspace: `CloneRepositoryJob` clones repo (show progress)
- Redirects to chat interface

## Chat Interface

### Header
Shows: repository name + location indicator (workspace path or site domain)

### Navigation
Repository-based, not site-based. List tasks grouped by repository.

### Actions
- **"Deploy to Site"** button - visible for workspace-based tasks
- **"Delete Workspace"** button - visible for workspace-based tasks

## Deploy to Site Flow

### Trigger
"Deploy to Site" button in chat interface (workspace tasks only).

### Modal: Smart Defaults
```
Subdomain: [________] .marin.sh

Preview:
  • Branch: {subdomain}
  • PHP: 8.4
  • Web directory: /public
  • Project type: Laravel

[▼ Advanced Options]

[Cancel]  [Deploy]
```

### Advanced Options (collapsed)
- PHP version
- Web directory
- Database name
- Isolated user toggle
- Custom branch name

### Deploy Process
Automated with live progress log:
1. Commit pending changes
2. Create branch (named after subdomain by default)
3. Push to origin
4. Create Ploi site via CLI
5. Install repository on site
6. Run first deploy
7. Request SSL certificate

User can cancel mid-process.

### On Completion
- Creates Site record linked to repository + branch
- Shows success with link to new site
- Option to continue working (now on the site) or close

## Workspace Management

### Location
Per-task workspaces at `/home/ploi/workspaces/{repo-name}-{task-uuid}`

### Cleanup
Manual only:
- "Delete Workspace" button on task
- Removes the workspace directory
- Task record remains (for history)

## Jobs

### CloneRepositoryJob
- Clones repository to workspace path
- Uses `git clone` with configured credentials
- Updates task with status

### DeployToSiteJob
- Commits any uncommitted changes
- Creates and pushes new branch
- Creates Ploi site via CLI
- Installs repository
- Triggers first deploy
- Requests SSL
- Creates Site record
- Updates task with site_id

## Implementation Order

1. Database migrations (Task + Site schema changes)
2. PloiService for site syncing
3. Update Task model and relationships
4. Update Site model and relationships
5. Sync Sites action on SiteResource
6. CloneRepositoryJob
7. New task creation UI (Livewire component)
8. Update chat interface (TaskChat)
9. Deploy modal and DeployToSiteJob
10. Workspace cleanup functionality
