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

test('chat and sidebar refresh uses filament-like neutral palette tokens', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->not->toContain('radial-gradient(60rem 28rem at 110% -20%');
    expect($css)->not->toContain('backdrop-filter: blur(8px);');
    expect($css)->toContain('var(--gray-50)');
    expect($css)->toContain('var(--primary-600)');
    expect($css)->not->toContain('rgb(var(--');
});

test('chat dark surfaces avoid extreme contrast in custom refresh block', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->not->toContain('background: var(--gray-950);');
    expect($css)->toContain('background: var(--gray-700);');
    expect($css)->toContain('background-color: var(--gray-600);');
});

test('chat dark surfaces and borders use softer filament-like contrast steps', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->not->toContain(".dark .chat-input-area {\n    background-color: var(--gray-900);");
    expect($css)->toContain(".dark .chat-input-area {\n    background-color: var(--gray-800);");

    expect($css)->not->toContain(".dark .chat-textarea {\n    background-color: var(--gray-900);");
    expect($css)->toContain(".dark .chat-textarea {\n    background-color: var(--gray-800);");

    expect($css)->not->toContain(".dark .sidebar-tab-content {\n    border-color: var(--gray-700);\n    background: var(--gray-900);");
    expect($css)->toContain(".dark .sidebar-tab-content {\n    border-color: var(--gray-700);\n    background: var(--gray-800);");

    expect($css)->toContain(".dark .chat-bubble-assistant {\n    background-color: var(--gray-800);\n    border-color: var(--gray-700);");
});

test('chat dark mode keeps neutral accents across controls', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain(".dark {\n    --chat-accent: var(--gray-600);");
    expect($css)->toContain(".dark .sidebar-tab-btn-active {\n    color: var(--gray-100);");
    expect($css)->toContain(".dark .chat-submit {\n    background-color: var(--gray-600);\n    border-color: var(--gray-500);");
    expect($css)->toContain(".dark .chat-provider-btn-active {\n    background: var(--gray-600);\n    border-color: var(--gray-500);");
    expect($css)->toContain(".dark .chat-generate-title-btn,\n.dark .chat-rename-btn {\n    background: var(--gray-700);\n    border-color: var(--gray-600);\n    color: var(--gray-100);");
});

test('dark chat workspace wrapper avoids black canvas with lifted panel surfaces', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain('.dark .chat-page-main {');
    expect($css)->toContain('background: var(--gray-800);');
    expect($css)->toContain('border: 1px solid var(--gray-700);');

    expect($css)->toContain('.dark .chat-container {');
    expect($css)->toContain('border-color: var(--gray-700);');

    expect($css)->toContain(".dark .chat-header {\n    background-color: var(--gray-800);");
    expect($css)->toContain(".dark .chat-messages {\n    background: var(--gray-700);");
});

test('dark input and workspace panels use neutral gray overrides without blue tint', function () {
    $css = file_get_contents(resource_path('css/filament/chat.css'));

    expect($css)->toContain('.dark .chat-textarea {');
    expect($css)->toContain('background-color: #2f333b !important;');
    expect($css)->toContain('border-color: #4a5160 !important;');
    expect($css)->toContain('.dark .sidebar-tab-content,');
    expect($css)->toContain('.dark .task-todo-list,');
    expect($css)->toContain('background: #2b2f36 !important;');
});
