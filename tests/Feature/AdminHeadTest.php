<?php

it('does not inline large filament css blobs in the admin head hook', function () {
    $src = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($src)->not->toContain("file_get_contents(resource_path('css/filament/chat.css'))");
    expect($src)->not->toContain("file_get_contents(resource_path('css/filament/ide.css'))");
});
