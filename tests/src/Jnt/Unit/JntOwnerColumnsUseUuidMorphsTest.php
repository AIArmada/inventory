<?php

declare(strict_types=1);

it('uses nullableMorphs for JNT owner columns', function (): void {
    $repoRoot = dirname(__DIR__, 4);

    $ordersOwnerMigration = $repoRoot.'/packages/jnt/database/migrations/2000_10_01_000006_add_owner_columns_to_jnt_orders_table.php';
    $relatedOwnerMigration = $repoRoot.'/packages/jnt/database/migrations/2000_10_01_000008_add_owner_columns_to_jnt_related_tables.php';

    $orders = file_get_contents($ordersOwnerMigration);
    $related = file_get_contents($relatedOwnerMigration);

    expect($orders)->toBeString();
    expect($orders)->toContain("nullableMorphs('owner')");
    expect($orders)->not->toContain("nullableUuidMorphs('owner')");

    expect($related)->toBeString();
    expect($related)->toContain("nullableMorphs('owner')");
    expect($related)->not->toContain("nullableUuidMorphs('owner')");
});
