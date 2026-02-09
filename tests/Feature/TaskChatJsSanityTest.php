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
