<?php

use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

it('defaults schema layout components to full width', function () {
    expect(Grid::make()->getColumnSpan())->toBe(['default' => 'full']);
    expect(Section::make()->getColumnSpan())->toBe(['default' => 'full']);
    expect(Fieldset::make()->getColumnSpan())->toBe(['default' => 'full']);
});
