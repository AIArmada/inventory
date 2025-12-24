<?php

declare(strict_types=1);

afterEach(function (): void {
    unsetEnvVar('COMMERCE_JSON_COLUMN_TYPE');

    unsetEnvVar('PRODUCTS_JSON_COLUMN_TYPE');
    unsetEnvVar('CUSTOMERS_JSON_COLUMN_TYPE');
    unsetEnvVar('TAX_JSON_COLUMN_TYPE');
    unsetEnvVar('FILAMENT_CART_JSON_COLUMN_TYPE');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for products', function (): void {
    unsetEnvVar('PRODUCTS_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/products/config/products.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('allows per-package override for products', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('PRODUCTS_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/products/config/products.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for customers', function (): void {
    unsetEnvVar('CUSTOMERS_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/customers/config/customers.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('allows per-package override for customers', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('CUSTOMERS_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/customers/config/customers.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for tax', function (): void {
    unsetEnvVar('TAX_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/tax/config/tax.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('allows per-package override for tax', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('TAX_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/tax/config/tax.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for filament cart', function (): void {
    unsetEnvVar('FILAMENT_CART_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/filament-cart/config/filament-cart.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('allows per-package override for filament cart', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('FILAMENT_CART_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/filament-cart/config/filament-cart.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

function unsetEnvVar(string $key): void
{
    putenv($key);

    unset($_ENV[$key], $_SERVER[$key]);
}

function repoPath(string $relativePath): string
{
    return dirname(__DIR__, 3) . '/' . $relativePath;
}
