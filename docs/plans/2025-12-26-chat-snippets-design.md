# Chat Snippets Design

## Overview

Reusable text snippets that can be quickly inserted into chat conversations. Users manage snippets in a Filament resource and access them via a tabbed sidebar in the chat interface.

## Data Model

### Snippet Model

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| user_id | foreignId | Owner of the snippet |
| name | string | Display name (e.g., "Scrappa Login") |
| content | text | The text to insert |
| sort_order | integer | Manual ordering (default: 0) |
| created_at | timestamp | |
| updated_at | timestamp | |

**Relationships:**
- `BelongsTo: User`

## UI Design

### Filament Resource (`/admin/snippets`)

Simple CRUD interface:
- **Table columns:** Name, Content (truncated preview), Created date
- **Form:** Name input + large textarea for content
- **Ordering:** sort_order field or drag-to-reorder

### Chat Sidebar (Tabbed)

Replace current file browser container with tabbed interface:

```
┌─────────────────────────────┐
│ [Files] [Snippets]          │
├─────────────────────────────┤
│                             │
│  Content based on active    │
│  tab selection              │
│                             │
└─────────────────────────────┘
```

**Files tab:** Current FileBrowser component (unchanged)

**Snippets tab:**
- List of user's snippets (name only, styled like file items)
- Click to insert content into chat textarea
- Empty state: "No snippets yet"

### Snippet Insertion Behavior

1. User clicks snippet in sidebar
2. Component dispatches: `$dispatch('insert-snippet', { content: '...' })`
3. Chat component listens and appends to `$prompt`
4. If textarea has existing text, prepend newline before snippet
5. Focus returns to textarea

## Implementation

### New Files

| File | Purpose |
|------|---------|
| `app/Models/Snippet.php` | Eloquent model |
| `database/migrations/..._create_snippets_table.php` | Schema |
| `database/factories/SnippetFactory.php` | Testing |
| `app/Filament/Resources/SnippetResource.php` | Admin CRUD |
| `app/Filament/Resources/SnippetResource/Pages/*.php` | Resource pages |
| `app/Livewire/SnippetBrowser.php` | Sidebar list component |
| `resources/views/livewire/snippet-browser.blade.php` | Snippet list view |

### Modified Files

| File | Changes |
|------|---------|
| `resources/views/filament/.../task-chat.blade.php` | Tabbed sidebar |
| `resources/views/filament/.../general-chat-page.blade.php` | Tabbed sidebar |
| `app/Livewire/TaskChat.php` | Add `insertSnippet()` listener |
| `app/Livewire/GeneralChatBox.php` | Add `insertSnippet()` listener |
| `resources/css/filament/chat.css` | Tab button styles |

## Out of Scope

- Variable/placeholder support in snippets
- Snippet categories or folders
- Sharing snippets between users
- Keyboard shortcuts for insertion
