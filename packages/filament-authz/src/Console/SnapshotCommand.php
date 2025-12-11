<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\FilamentAuthz\Models\PermissionSnapshot;
use AIArmada\FilamentAuthz\Services\PermissionVersioningService;
use Illuminate\Console\Command;

class SnapshotCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'authz:snapshot
        {action=list : Action to perform (create, list, compare, rollback)}
        {--name= : Name for the snapshot}
        {--description= : Description for the snapshot}
        {--from= : ID of first snapshot for comparison}
        {--to= : ID of second snapshot for comparison}
        {--snapshot= : Snapshot ID for rollback}
        {--dry-run : Preview rollback without applying}
        {--force : Force rollback without confirmation}';

    /**
     * @var string
     */
    protected $description = 'Manage permission snapshots';

    public function handle(PermissionVersioningService $versioning): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'create' => $this->createSnapshot($versioning),
            'list' => $this->listSnapshots($versioning),
            'compare' => $this->compareSnapshots($versioning),
            'rollback' => $this->rollbackSnapshot($versioning),
            default => $this->invalidAction($action),
        };
    }

    protected function createSnapshot(PermissionVersioningService $versioning): int
    {
        $name = $this->option('name');

        if (! $name) {
            $name = 'snapshot_' . now()->format('Y-m-d_H-i-s');
        }

        $description = $this->option('description');

        $snapshot = $versioning->createSnapshot($name, $description);

        $this->info("✅ Created snapshot: {$snapshot->name} ({$snapshot->id})");

        return Command::SUCCESS;
    }

    protected function listSnapshots(PermissionVersioningService $versioning): int
    {
        $snapshots = $versioning->listSnapshots();

        if ($snapshots->isEmpty()) {
            $this->info('No snapshots found.');

            return Command::SUCCESS;
        }

        $rows = $snapshots->map(fn (PermissionSnapshot $snapshot) => [
            $snapshot->id,
            $snapshot->name,
            $snapshot->description ?? '-',
            $snapshot->created_at?->format('Y-m-d H:i:s') ?? '-',
            count($snapshot->getRoles()) . ' roles, ' . count($snapshot->getPermissions()) . ' perms',
        ])->toArray();

        $this->table(['ID', 'Name', 'Description', 'Created', 'State'], $rows);

        return Command::SUCCESS;
    }

    protected function compareSnapshots(PermissionVersioningService $versioning): int
    {
        $fromId = $this->option('from');
        $toId = $this->option('to');

        if (! $fromId || ! $toId) {
            $this->error('Both --from and --to snapshot IDs are required.');

            return Command::FAILURE;
        }

        $from = PermissionSnapshot::find($fromId);
        $to = PermissionSnapshot::find($toId);

        if ($from === null || $to === null) {
            $this->error('One or both snapshots not found.');

            return Command::FAILURE;
        }

        $diff = $versioning->compare($from, $to);

        $this->info("\n📊 Comparison: {$from->name} → {$to->name}\n");

        // Roles
        $this->line('<fg=cyan>Roles:</>');
        if (! empty($diff['roles']['added'])) {
            foreach ($diff['roles']['added'] as $role) {
                $this->line("  <fg=green>+ {$role}</>");
            }
        }
        if (! empty($diff['roles']['removed'])) {
            foreach ($diff['roles']['removed'] as $role) {
                $this->line("  <fg=red>- {$role}</>");
            }
        }
        if (empty($diff['roles']['added']) && empty($diff['roles']['removed'])) {
            $this->line('  No changes');
        }

        // Permissions
        $this->newLine();
        $this->line('<fg=cyan>Permissions:</>');
        if (! empty($diff['permissions']['added'])) {
            foreach ($diff['permissions']['added'] as $perm) {
                $this->line("  <fg=green>+ {$perm}</>");
            }
        }
        if (! empty($diff['permissions']['removed'])) {
            foreach ($diff['permissions']['removed'] as $perm) {
                $this->line("  <fg=red>- {$perm}</>");
            }
        }
        if (empty($diff['permissions']['added']) && empty($diff['permissions']['removed'])) {
            $this->line('  No changes');
        }

        return Command::SUCCESS;
    }

    protected function rollbackSnapshot(PermissionVersioningService $versioning): int
    {
        $snapshotId = $this->option('snapshot');

        if (! $snapshotId) {
            $this->error('--snapshot ID is required for rollback.');

            return Command::FAILURE;
        }

        $snapshot = PermissionSnapshot::find($snapshotId);

        if ($snapshot === null) {
            $this->error('Snapshot not found.');

            return Command::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $preview = $versioning->previewRollback($snapshot);
            $this->info("\n🔍 Preview rollback to: {$snapshot->name}\n");

            // Show what would change
            $this->line('<fg=yellow>This is a dry-run. No changes will be made.</>');
            $this->newLine();

            if (! empty($preview['roles']['added'])) {
                $this->line('Roles to add: ' . implode(', ', $preview['roles']['added']));
            }
            if (! empty($preview['roles']['removed'])) {
                $this->line('Roles to remove: ' . implode(', ', $preview['roles']['removed']));
            }
            if (! empty($preview['permissions']['added'])) {
                $this->line('Permissions to add: ' . count($preview['permissions']['added']));
            }
            if (! empty($preview['permissions']['removed'])) {
                $this->line('Permissions to remove: ' . count($preview['permissions']['removed']));
            }

            return Command::SUCCESS;
        }

        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                "⚠️  This will replace all current permissions with snapshot '{$snapshot->name}'. Continue?",
                false
            );

            if (! $confirmed) {
                $this->line('Rollback cancelled.');

                return Command::SUCCESS;
            }
        }

        $result = $versioning->rollback($snapshot);

        if ($result->success) {
            $this->info("✅ Successfully rolled back to: {$snapshot->name}");
        } else {
            $this->error('Rollback failed.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function invalidAction(string $action): int
    {
        $this->error("Unknown action: {$action}");
        $this->line('Available actions: create, list, compare, rollback');

        return Command::FAILURE;
    }
}
