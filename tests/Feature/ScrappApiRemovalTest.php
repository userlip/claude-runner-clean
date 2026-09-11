<?php

use Illuminate\Support\Facades\Schema;

test('removed scrapp api storage and workbench routes stay unavailable', function () {
    expect(Schema::hasTable('scrapp_apis'))->toBeFalse()
        ->and(Schema::hasColumn('tasks', 'scrapp_api_id'))->toBeFalse();

    $this->get('/workbench/scrapp-apis')->assertNotFound();
    $this->get('/workbench/scrapp-apis/create')->assertNotFound();
    $this->get('/workbench/scrapp-apis/1/edit')->assertNotFound();
});
