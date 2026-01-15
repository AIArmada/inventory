<?php

declare(strict_types=1);

use Illuminate\Support\Str;

function cartPerformanceMigrationPath(): string
{
    $repoRoot = dirname(__DIR__, 4);

    return $repoRoot.'/packages/cart/database/migrations/2000_02_01_000006_add_performance_indexes_to_carts_table.php';
}

it('does not use NOW() in PostgreSQL index predicates', function (): void {
    $path = cartPerformanceMigrationPath();

    $contents = file_get_contents($path);

    expect($contents)->toBeString();
    expect(Str::contains($contents, 'NOW()', true))->toBeFalse();
});

it('does not create analytics index by comparing json to json', function (): void {
    $path = cartPerformanceMigrationPath();

    $contents = file_get_contents($path);

    expect($contents)->toBeString();
    expect((bool) preg_match("/items\\s*!=\\s*'\\[\\]'::json(?!b)/i", $contents))->toBeFalse();
});
