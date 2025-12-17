<?php

declare(strict_types=1);

use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;

it('registers filament component macros', function (): void {
    expect(Panel::hasMacro('softShadow'))->toBeTrue();
    expect(Split::hasMacro('glow'))->toBeTrue();
    expect(Stack::hasMacro('carded'))->toBeTrue();
    expect(Fieldset::hasMacro('inlineLabelled'))->toBeTrue();
});

it('can call the registered macros without error', function (): void {
    $panel = Panel::make([])->softShadow();
    $split = Split::make([])->glow();
    $stack = Stack::make([])->carded();
    $fieldset = Fieldset::make('Test')->inlineLabelled();

    expect($panel)->toBeInstanceOf(Panel::class);
    expect($split)->toBeInstanceOf(Split::class);
    expect($stack)->toBeInstanceOf(Stack::class);
    expect($fieldset)->toBeInstanceOf(Fieldset::class);
});
