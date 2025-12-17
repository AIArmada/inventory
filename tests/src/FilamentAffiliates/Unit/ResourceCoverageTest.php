<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

it('AffiliateProgramResource configures form schema', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateProgramResource::form($schema);

    expect(true)->toBeTrue();
});

it('AffiliateProgramResource configures table schema', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('filters')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    AffiliateProgramResource::table($table);

    expect(true)->toBeTrue();
});

it('AffiliateFraudSignalResource configures form schema', function (): void {
    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    AffiliateFraudSignalResource::form($schema);

    expect(true)->toBeTrue();
});

it('AffiliateFraudSignalResource configures table schema', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('filters')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();
    $table->shouldReceive('defaultSort')->once()->andReturnSelf();

    AffiliateFraudSignalResource::table($table);

    expect(true)->toBeTrue();
});
