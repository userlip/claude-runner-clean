<?php

test('task chat blade does not contain broken quote escapes that corrupt html attributes', function () {
    $src = file_get_contents(resource_path('views/livewire/task-chat.blade.php'));

    // This substring breaks the surrounding `x-data="..."` HTML attribute and causes JS to render as text.
    expect($src)->not->toContain("\\\"'\\\":");
});

test('task chat cached preview renderer avoids raw double quotes inside x-data attribute', function () {
    $src = file_get_contents(resource_path('views/livewire/task-chat.blade.php'));

    // These patterns introduce raw `"` characters into the `x-data="..."` attribute, which breaks the attribute
    // and leaks the JS source into the DOM as text.
    expect($src)->not->toContain('class="${outerClass}"');
    expect($src)->not->toContain('/[&<>"\']/g');
    expect($src)->not->toContain('x-data="..."');
});

test('task chat keeps scroll stable during live updates when user is not near bottom', function () {
    $src = file_get_contents(resource_path('views/livewire/task-chat.blade.php'));
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($src)->toContain('const previousScrollTop = this.$refs.messages.scrollTop;');
    expect($src)->toContain('this.$refs.messages.scrollTop = previousScrollTop;');
    expect($css)->toContain('overflow-anchor: none;');
});

test('chat and sidebar refresh defines tokyo night palette tokens', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->not->toContain('radial-gradient(60rem 28rem at 110% -20%');
    expect($css)->not->toContain('backdrop-filter: blur(8px);');
    expect($css)->toContain('--chat-tokyo-fg: #959cbd;');
    expect($css)->toContain('--chat-tokyo-fg-active: #bdc7f0;');
    expect($css)->toContain('--chat-tokyo-fg-inactive: #787c99;');
    expect($css)->toContain('--chat-tokyo-fg-dim: #696d87;');
    expect($css)->toContain('--chat-tokyo-border: #3d59a1;');
    expect($css)->toContain('--chat-tokyo-bg: #202330;');
    expect($css)->toContain('--chat-bg-dark: var(--chat-tokyo-bg);');
    expect($css)->toContain('--chat-fg-dark: var(--chat-tokyo-fg);');
});

test('chat dark surfaces apply tokyo night background and foreground consistently', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain(".dark .chat-input-area {\n    background-color: var(--chat-tokyo-bg-elevated);");
    expect($css)->toContain(".dark .chat-textarea {\n    background-color: var(--chat-bg-dark);");
    expect($css)->toContain('color: var(--chat-fg-dark);');
    expect($css)->toContain('border-color: var(--chat-accent-border);');
});

test('chat dark controls use tokyo night accent colors', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain(".dark .sidebar-tab-btn-active {\n    color: var(--chat-tokyo-fg-active);");
    expect($css)->toContain(".dark .chat-submit {\n    background-color: var(--chat-accent);");
    expect($css)->toContain(".dark .chat-submit:hover {\n    background-color: color-mix(in srgb, var(--chat-accent) 74%, var(--chat-tokyo-fg-active));");
    expect($css)->toContain(".dark .chat-provider-btn-active {\n    background-color: var(--chat-accent) !important;");
});

test('chat dark mode applies tokyo night base palette to workspace wrappers', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain(".dark .chat-page-main {\n    background: var(--chat-tokyo-bg-soft);");
    expect($css)->toContain(".dark .chat-container {\n    border-color: var(--chat-accent-border);");
    expect($css)->toContain(".dark .chat-header {\n    background-color: var(--chat-tokyo-bg-elevated);");
    expect($css)->toContain(".dark .chat-messages {\n    background: var(--chat-bg-dark);");
});

test('dark chat workspace wrapper avoids black canvas with lifted panel surfaces', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain('.dark .chat-page-main {');
    expect($css)->toContain('background: var(--chat-tokyo-bg-soft);');
    expect($css)->toContain('border: 1px solid var(--chat-accent-border);');

    expect($css)->toContain('.dark .chat-container {');
    expect($css)->toContain('border-color: var(--chat-accent-border);');

    expect($css)->toContain(".dark .chat-header {\n    background-color: var(--chat-tokyo-bg-elevated);");
    expect($css)->toContain(".dark .chat-messages {\n    background: var(--chat-bg-dark);");
});

test('dark input and workspace panels use neutral gray overrides without blue tint', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain('.dark .chat-textarea {');
    expect($css)->toContain('background-color: var(--chat-bg-dark) !important;');
    expect($css)->toContain('border-color: var(--chat-accent-border) !important;');
    expect($css)->toContain('.dark .sidebar-tab-content,');
    expect($css)->toContain('.dark .task-todo-list,');
    expect($css)->toContain('background: var(--chat-tokyo-bg-elevated) !important;');
});

test('chat input focus accent in dark mode follows tokyo night border accent', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain(".dark .chat-textarea:focus {\n    border-color: var(--chat-tokyo-border);");
    expect($css)->toContain('box-shadow: 0 0 0 2px color-mix(in srgb, var(--chat-tokyo-border) 35%, transparent);');
    expect($css)->not->toContain(".dark .chat-textarea:focus {\n    border-color: var(--chat-keyword);");
});
