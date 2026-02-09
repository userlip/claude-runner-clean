<?php

test('task chat persists draft across spa navigation', function () {
    $src = file_get_contents(resource_path('views/livewire/task-chat.blade.php'));

    expect($src)->toContain('sessionStorage');
    expect($src)->toContain('cr:chat-draft');
    expect($src)->toContain('livewire:navigating');
});

test('task chat caches recent messages locally for fast perceived navigation', function () {
    $src = file_get_contents(resource_path('views/livewire/task-chat.blade.php'));

    expect($src)->toContain('localStorage');
    expect($src)->toContain('cr:chat-cache');
    expect($src)->toContain('cachedHistory');
});
