<?php

declare(strict_types=1);

it('does not hardcode JSON columns in recovery migrations', function (): void {
    $repoRoot = dirname(__DIR__, 4);

    $paths = [
        $repoRoot . '/packages/filament-cart/database/migrations/2000_08_01_000006_create_cart_recovery_campaigns_table.php',
        $repoRoot . '/packages/filament-cart/database/migrations/2000_08_01_000008_create_cart_recovery_attempts_table.php',
    ];

    foreach ($paths as $path) {
        expect($path)->toBeFile();

        $content = file_get_contents($path);

        expect($content)
            ->toBeString()
            ->not->toContain("->json('");
    }
});
