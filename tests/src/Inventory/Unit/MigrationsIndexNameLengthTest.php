<?php

declare(strict_types=1);

it('uses explicit short index names to avoid Postgres truncation collisions', function (): void {
    $repoRoot = dirname(__DIR__, 4);

    $supplierLeadTimesPath = $repoRoot . '/packages/inventory/database/migrations/2000_09_01_000017_create_inventory_supplier_leadtimes_table.php';
    $supplierLeadTimes = file_get_contents($supplierLeadTimesPath);

    $reorderSuggestionsPath = $repoRoot . '/packages/inventory/database/migrations/2000_09_01_000018_create_inventory_reorder_suggestions_table.php';
    $reorderSuggestions = file_get_contents($reorderSuggestionsPath);

    expect($supplierLeadTimes)->toBeString();
    expect($supplierLeadTimes)->toContain('inv_supp_lead_invable_active_idx');

    expect($reorderSuggestions)->toBeString();
    expect($reorderSuggestions)->toContain('inv_reorder_invable_status_idx');
});
