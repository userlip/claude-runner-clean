<?php

test('livewire payload limit allows image chat requests over 1MB', function () {
    expect(config('livewire.payload.max_size'))->toBeGreaterThanOrEqual(4 * 1024 * 1024);
});
