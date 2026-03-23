# MCP Server Management System - PRD

**Status:** Draft
**Created:** 2026-03-23
**Author:** Assistant

---

## 1. Executive Summary

Build a centralized MCP (Model Context Protocol) server management system within the Claude Runner Laravel/Filament admin panel. This system will allow a single admin to manage MCP configurations for Claude Code and Codex CLI tools, with configurations stored per-server and available to all folders for the `ploi` user.

---

## 2. User Story

As a server administrator, I want to manage MCP server configurations through a web interface so that I can:
- View all configured MCP servers and their status
- Add new MCP servers (command-based or SSE-based)
- Edit existing MCP server configurations
- Enable/disable MCP servers without deleting them
- Test MCP server connections to verify they work
- Export configurations for Claude Code and Codex CLI

---

## 3. Requirements

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| A | **View MCP Servers**: List all MCP servers with name, type, status, and connection status | Must |
| B | **Add MCP Server**: Create new MCP server with name, transport type, command/URL, args, env vars | Must |
| C | **Edit MCP Server**: Modify existing MCP server configurations | Must |
| D | **Enable/Disable**: Toggle MCP server active state without deletion | Must |
| E | **Test Connection**: Verify MCP server is reachable and responding | Must |
| F | **Export Config**: Generate `.mcp.json` files for Claude Code and Codex CLI | Must |
| G | **Auto-apply**: Configurations auto-apply to all folders for the ploi user | Should |

### 3.2 MCP Types Supported

**Command-based MCPs:**
- Command (e.g., `php`, `npx`, `node`)
- Arguments array
- Environment variables (key-value pairs)

**SSE-based MCPs:**
- Server URL
- Optional authentication headers

### 3.3 Configuration Storage

- Store configurations in database table `mcp_servers`
- Write to `~/.claude/.mcp.json` (Claude Code format)
- Write to appropriate Codex CLI config location (TBD)
- Make available system-wide for `ploi` user

---

## 4. Database Schema

```php
// mcp_servers table
Schema::create('mcp_servers', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();           // Server identifier (e.g., "laravel-boost")
    $table->string('display_name');             // Human-readable name
    $table->text('description')->nullable();
    $table->enum('transport', ['command', 'sse']);
    $table->boolean('is_enabled')->default(true);

    // Command-based fields
    $table->string('command')->nullable();      // e.g., "php", "npx"
    $table->json('args')->nullable();           // Array of arguments
    $table->json('env_vars')->nullable();       // Key-value env variables (encrypted)

    // SSE-based fields
    $table->string('server_url')->nullable();
    $table->json('headers')->nullable();        // Authentication headers (encrypted)

    $table->timestamp('last_tested_at')->nullable();
    $table->string('last_test_status')->nullable(); // 'success', 'failed', 'unknown'
    $table->text('last_test_message')->nullable();

    $table->timestamps();
    $table->softDeletes();
});
```

---

## 5. UI Design

### 5.1 MCP Servers List Page

**Layout:** Filament Resource Table

| Column | Description |
|--------|-------------|
| Name | Server name with status indicator (enabled/disabled) |
| Transport | Badge: "Command" or "SSE" |
| Status | Connection test result (green/yellow/red icon) |
| Last Tested | Relative time ago |
| Actions | Edit, Delete, Test, View JSON |

**Actions:**
- **Add MCP Server** button → Create modal
- **Export Config** action → Download `.mcp.json`
- **Bulk Test** action → Test all enabled servers

### 5.2 Create/Edit Form

**Common Fields:**
- Name (slug format, unique)
- Display Name
- Description (textarea)
- Transport Type (radio: Command / SSE)
- Enabled (toggle)

**Command Transport Fields:**
- Command (text, placeholder: "php", "npx")
- Arguments (repeater with key-value, or textarea)
- Environment Variables (key-value repeater, encrypted)

**SSE Transport Fields:**
- Server URL (URL input)
- Headers (key-value repeater, encrypted)

### 5.3 Test Connection

**Test Button on Form:**
- Validates configuration format
- Attempts to spawn/connection test
- Shows success/failure message with details
- Updates `last_tested_at` and `last_test_status`

**Test All Action:**
- Runs tests for all enabled servers
- Shows aggregate results
- Option to disable failed servers

---

## 6. Configuration Export

### 6.1 Claude Code Format (`~/.claude/.mcp.json`)

```json
{
  "mcpServers": {
    "laravel-boost": {
      "command": "php",
      "args": ["artisan", "boost:mcp"]
    },
    "playwright": {
      "command": "npx",
      "args": ["@playwright/mcp@latest"]
    },
    "project-db": {
      "command": "node",
      "args": ["/path/to/mcp-server/index.js"],
      "env": {
        "DB_HOST": "localhost",
        "DB_PORT": "3306"
      }
    }
  }
}
```

### 6.2 Codex CLI Format

TBD based on Codex CLI documentation. Likely similar structure but different file location.

---

## 7. Implementation Tasks

### Phase 1: Core Infrastructure
- [ ] Create migration for `mcp_servers` table
- [ ] Create `McpServer` Eloquent model with casts and accessors
- [ ] Create `McpServerExporter` service class
- [ ] Create `McpConnectionTester` service class

### Phase 2: Filament Resource
- [ ] Create `McpServerResource` with List, Create, Edit pages
- [ ] Implement table with all columns and actions
- [ ] Create form with conditional fields based on transport type
- [ ] Add "Test Connection" action

### Phase 3: Configuration Export
- [ ] Implement JSON export for Claude Code format
- [ ] Write to `~/.claude/.mcp.json` on save
- [ ] Implement file watcher or sync mechanism
- [ ] Research and implement Codex CLI format

### Phase 4: Testing & Polish
- [ ] Add unit tests for exporter service
- [ ] Add feature tests for resource CRUD operations
- [ ] Add connection test simulation/mocking
- [ ] Document usage in admin panel

---

## 8. Technical Considerations

### Security
- Encrypt sensitive fields (env_vars, headers) at rest
- Validate commands don't allow arbitrary code execution
- Restrict access to admin users only

### Performance
- Cache configuration exports to avoid regenerating on every read
- Test connections asynchronously (queue jobs)

### File Permissions
- Ensure `ploi` user can read exported config files
- Set appropriate file permissions (600 for sensitive configs)

---

## 9. Open Questions

1. **Codex CLI config location:** Need to research where Codex CLI stores its MCP config
2. **SSE MCP support:** Confirm SSE-based MCPs are needed (most are command-based)
3. **Auto-apply timing:** Should configs auto-apply on save or require explicit "Deploy" action?
4. **Queue workers:** Do queue workers need to be restarted when MCP configs change?

---

## 10. Acceptance Criteria

- [ ] Admin can view all MCP servers in a table
- [ ] Admin can add command-based MCP server
- [ ] Admin can add SSE-based MCP server
- [ ] Admin can edit existing MCP servers
- [ ] Admin can enable/disable MCP servers
- [ ] Admin can test MCP server connections
- [ ] Configurations export to `.mcp.json` format
- [ ] Configurations are available to all folders for ploi user
- [ ] All operations are restricted to admin users
