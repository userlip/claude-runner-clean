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

test('chat and sidebar refresh defines monokai-inspired palette tokens', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->not->toContain('radial-gradient(60rem 28rem at 110% -20%');
    expect($css)->not->toContain('backdrop-filter: blur(8px);');
    expect($css)->toContain('--chat-bg-dark: #272822;');
    expect($css)->toContain('--chat-fg-dark: #f8f8f2;');
    expect($css)->toContain('--chat-muted-dark: #75715e;');
    expect($css)->toContain('--chat-keyword: #f92672;');
    expect($css)->toContain('--chat-function: #a6e22e;');
    expect($css)->toContain('--chat-string: #e6db74;');
    expect($css)->toContain('--chat-number: #ae81ff;');
    expect($css)->toContain('--chat-operator: #fd971f;');
});

test('chat dark surfaces apply monokai background and foreground consistently', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain(".dark .chat-input-area {\n    background-color: var(--chat-bg-dark);");
    expect($css)->toContain(".dark .chat-textarea {\n    background-color: var(--chat-bg-dark);");
    expect($css)->toContain('color: var(--chat-fg-dark);');
    expect($css)->toContain('border-color: var(--chat-muted-dark);');
});

test('chat dark controls use monokai accent colors instead of blue defaults', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain(".dark .sidebar-tab-btn-active {\n    color: #272822;\n    border-bottom: none;\n    border: 1px solid var(--chat-number);\n    background: var(--chat-number);");
    expect($css)->toContain(".dark .chat-submit {\n    background-color: var(--chat-operator);\n    border-color: var(--chat-operator);");
    expect($css)->toContain(".dark .chat-submit:hover {\n    background-color: var(--chat-keyword);");
    expect($css)->toContain(".dark .chat-provider-btn-active {\n    background: var(--chat-function);\n    border-color: var(--chat-function);");
});

test('chat dark mode applies monokai base palette to workspace wrappers', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain(".dark .chat-page-main {\n    background: var(--chat-bg-dark);");
    expect($css)->toContain(".dark .chat-container {\n    border-color: var(--chat-muted-dark);");
    expect($css)->toContain(".dark .chat-header {\n    background-color: var(--chat-bg-dark);");
    expect($css)->toContain(".dark .chat-messages {\n    background: var(--chat-bg-dark);");
});

test('dark chat workspace wrapper avoids black canvas with lifted panel surfaces', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain('.dark .chat-page-main {');
    expect($css)->toContain('background: var(--chat-bg-dark);');
    expect($css)->toContain('border: 1px solid var(--chat-muted-dark);');

    expect($css)->toContain('.dark .chat-container {');
    expect($css)->toContain('border-color: var(--chat-muted-dark);');

    expect($css)->toContain(".dark .chat-header {\n    background-color: var(--chat-bg-dark);");
    expect($css)->toContain(".dark .chat-messages {\n    background: var(--chat-bg-dark);");
});

test('dark input and workspace panels use neutral gray overrides without blue tint', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain('.dark .chat-textarea {');
    expect($css)->toContain('background-color: var(--chat-bg-dark) !important;');
    expect($css)->toContain('border-color: var(--chat-muted-dark) !important;');
    expect($css)->toContain('.dark .sidebar-tab-content,');
    expect($css)->toContain('.dark .task-todo-list,');
    expect($css)->toContain('background: var(--chat-bg-dark) !important;');
});

test('chat input focus accent in dark mode is non-error and avoids red keyword tone', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain(".dark .chat-textarea:focus {\n    border-color: var(--chat-number);");
    expect($css)->toContain('box-shadow: 0 0 0 2px color-mix(in srgb, var(--chat-number) 35%, transparent);');
    expect($css)->not->toContain(".dark .chat-textarea:focus {\n    border-color: var(--chat-keyword);");
});
