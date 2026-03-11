<?php

use App\Filament\Resources\Personas\Widgets\PersonaStatsWidget;

test('persona stats widget polling interval override is instance-based', function () {
    $property = new ReflectionProperty(PersonaStatsWidget::class, 'pollingInterval');

    expect($property->isStatic())->toBeFalse();
});
