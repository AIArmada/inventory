<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Models\PermissionSnapshot;
use AIArmada\FilamentAuthz\Services\PermissionVersioningService;
use AIArmada\FilamentAuthz\Services\RollbackResult;
use Illuminate\Console\Command;

describe('SnapshotCommand', function (): void {
    it('is registered as artisan command', function (): void {
        $this->artisan('authz:snapshot', ['action' => 'list'])
            ->assertSuccessful();
    });
});

describe('SnapshotCommand::list action', function (): void {
    it('shows message when no snapshots exist', function (): void {
        $this->mock(PermissionVersioningService::class, function ($mock): void {
            $mock->shouldReceive('listSnapshots')
                ->once()
                ->andReturn(collect());
        });

        $this->artisan('authz:snapshot', ['action' => 'list'])
            ->expectsOutput('No snapshots found.')
            ->assertSuccessful();
    });

    it('displays snapshots in table format', function (): void {
        $snapshot = PermissionSnapshot::create([
            'name' => 'test_snapshot',
            'description' => 'Test description',
            'state' => ['roles' => [], 'permissions' => []],
            'hash' => md5('test_snapshot'),
        ]);

        $this->mock(PermissionVersioningService::class, function ($mock) use ($snapshot): void {
            $mock->shouldReceive('listSnapshots')
                ->once()
                ->andReturn(collect([$snapshot]));
        });

        $this->artisan('authz:snapshot', ['action' => 'list'])
            ->assertSuccessful();
    });
});

describe('SnapshotCommand::create action', function (): void {
    it('creates snapshot with auto-generated name', function (): void {
        $snapshot = PermissionSnapshot::create([
            'name' => 'snapshot_2024-01-01_00-00-00',
            'state' => ['roles' => [], 'permissions' => []],
            'hash' => md5('snapshot_2024-01-01_00-00-00'),
        ]);

        $this->mock(PermissionVersioningService::class, function ($mock) use ($snapshot): void {
            $mock->shouldReceive('createSnapshot')
                ->once()
                ->andReturn($snapshot);
        });

        $this->artisan('authz:snapshot', ['action' => 'create'])
            ->expectsOutputToContain('Created snapshot')
            ->assertSuccessful();
    });

    it('creates snapshot with custom name', function (): void {
        $snapshot = PermissionSnapshot::create([
            'name' => 'my_custom_snapshot',
            'state' => ['roles' => [], 'permissions' => []],
            'hash' => md5('my_custom_snapshot'),
        ]);

        $this->mock(PermissionVersioningService::class, function ($mock) use ($snapshot): void {
            $mock->shouldReceive('createSnapshot')
                ->with('my_custom_snapshot', null)
                ->once()
                ->andReturn($snapshot);
        });

        $this->artisan('authz:snapshot', ['action' => 'create', '--name' => 'my_custom_snapshot'])
            ->expectsOutputToContain('Created snapshot')
            ->assertSuccessful();
    });

    it('creates snapshot with description', function (): void {
        $snapshot = PermissionSnapshot::create([
            'name' => 'my_snapshot',
            'description' => 'My description',
            'state' => ['roles' => [], 'permissions' => []],
            'hash' => md5('my_snapshot'),
        ]);

        $this->mock(PermissionVersioningService::class, function ($mock) use ($snapshot): void {
            $mock->shouldReceive('createSnapshot')
                ->with('my_snapshot', 'My description')
                ->once()
                ->andReturn($snapshot);
        });

        $this->artisan('authz:snapshot', [
            'action' => 'create',
            '--name' => 'my_snapshot',
            '--description' => 'My description',
        ])
            ->expectsOutputToContain('Created snapshot')
            ->assertSuccessful();
    });
});

describe('SnapshotCommand::compare action', function (): void {
    it('requires both from and to options', function (): void {
        $this->artisan('authz:snapshot', ['action' => 'compare'])
            ->expectsOutput('Both --from and --to snapshot IDs are required.')
            ->assertExitCode(Command::FAILURE);
    });

    it('requires to option when from is provided', function (): void {
        $this->artisan('authz:snapshot', ['action' => 'compare', '--from' => 'id-1'])
            ->expectsOutput('Both --from and --to snapshot IDs are required.')
            ->assertExitCode(Command::FAILURE);
    });

    it('fails when snapshots not found', function (): void {
        $this->artisan('authz:snapshot', [
            'action' => 'compare',
            '--from' => 'nonexistent-1',
            '--to' => 'nonexistent-2',
        ])
            ->expectsOutput('One or both snapshots not found.')
            ->assertExitCode(Command::FAILURE);
    });

    it('shows comparison results', function (): void {
        $from = PermissionSnapshot::create([
            'name' => 'from_snapshot',
            'state' => ['roles' => ['admin'], 'permissions' => ['view']],
            'hash' => md5('from_snapshot'),
        ]);

        $to = PermissionSnapshot::create([
            'name' => 'to_snapshot',
            'state' => ['roles' => ['admin', 'editor'], 'permissions' => ['view', 'edit']],
            'hash' => md5('to_snapshot'),
        ]);

        $this->mock(PermissionVersioningService::class, function ($mock): void {
            $mock->shouldReceive('compare')
                ->once()
                ->andReturn([
                    'roles' => ['added' => ['editor'], 'removed' => []],
                    'permissions' => ['added' => ['edit'], 'removed' => []],
                ]);
        });

        $this->artisan('authz:snapshot', [
            'action' => 'compare',
            '--from' => $from->id,
            '--to' => $to->id,
        ])
            ->assertSuccessful();
    });
});

describe('SnapshotCommand::rollback action', function (): void {
    it('requires snapshot option', function (): void {
        $this->artisan('authz:snapshot', ['action' => 'rollback'])
            ->expectsOutput('--snapshot ID is required for rollback.')
            ->assertExitCode(Command::FAILURE);
    });

    it('fails when snapshot not found', function (): void {
        $this->artisan('authz:snapshot', [
            'action' => 'rollback',
            '--snapshot' => 'nonexistent-id',
        ])
            ->expectsOutput('Snapshot not found.')
            ->assertExitCode(Command::FAILURE);
    });

    it('shows dry run preview', function (): void {
        $snapshot = PermissionSnapshot::create([
            'name' => 'rollback_snapshot',
            'state' => ['roles' => ['admin'], 'permissions' => ['view']],
            'hash' => md5('rollback_snapshot'),
        ]);

        $this->mock(PermissionVersioningService::class, function ($mock): void {
            $mock->shouldReceive('previewRollback')
                ->once()
                ->andReturn([
                    'roles' => ['added' => ['admin'], 'removed' => []],
                    'permissions' => ['added' => ['view'], 'removed' => []],
                ]);
        });

        $this->artisan('authz:snapshot', [
            'action' => 'rollback',
            '--snapshot' => $snapshot->id,
            '--dry-run' => true,
        ])
            ->assertSuccessful();
    });

    it('performs rollback with force option', function (): void {
        $snapshot = PermissionSnapshot::create([
            'name' => 'rollback_snapshot',
            'state' => ['roles' => ['admin'], 'permissions' => ['view']],
            'hash' => md5('rollback_force'),
        ]);

        $result = new RollbackResult(
            success: true,
            snapshot: $snapshot,
            isDryRun: false
        );

        $this->mock(PermissionVersioningService::class, function ($mock) use ($result): void {
            $mock->shouldReceive('rollback')
                ->once()
                ->andReturn($result);
        });

        $this->artisan('authz:snapshot', [
            'action' => 'rollback',
            '--snapshot' => $snapshot->id,
            '--force' => true,
        ])
            ->expectsOutputToContain('Successfully rolled back to')
            ->assertSuccessful();
    });

    it('prompts for confirmation without force', function (): void {
        $snapshot = PermissionSnapshot::create([
            'name' => 'rollback_snapshot',
            'state' => ['roles' => [], 'permissions' => []],
            'hash' => md5('rollback_confirm'),
        ]);

        $this->artisan('authz:snapshot', [
            'action' => 'rollback',
            '--snapshot' => $snapshot->id,
        ])
            ->expectsConfirmation(
                "⚠️  This will replace all current permissions with snapshot '{$snapshot->name}'. Continue?",
                'no'
            )
            ->expectsOutput('Rollback cancelled.')
            ->assertSuccessful();
    });

    it('handles failed rollback', function (): void {
        $snapshot = PermissionSnapshot::create([
            'name' => 'rollback_snapshot',
            'state' => ['roles' => [], 'permissions' => []],
            'hash' => md5('rollback_failed'),
        ]);

        $result = new RollbackResult(
            success: false,
            snapshot: $snapshot,
            isDryRun: false
        );

        $this->mock(PermissionVersioningService::class, function ($mock) use ($result): void {
            $mock->shouldReceive('rollback')
                ->once()
                ->andReturn($result);
        });

        $this->artisan('authz:snapshot', [
            'action' => 'rollback',
            '--snapshot' => $snapshot->id,
            '--force' => true,
        ])
            ->expectsOutput('Rollback failed.')
            ->assertExitCode(Command::FAILURE);
    });
});

describe('SnapshotCommand::invalid action', function (): void {
    it('shows error for unknown action', function (): void {
        $this->artisan('authz:snapshot', ['action' => 'unknown'])
            ->expectsOutput('Unknown action: unknown')
            ->expectsOutput('Available actions: create, list, compare, rollback')
            ->assertExitCode(Command::FAILURE);
    });
});
