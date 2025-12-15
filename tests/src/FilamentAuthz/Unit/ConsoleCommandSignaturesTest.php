<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\InstallTraitCommand;
use AIArmada\FilamentAuthz\Console\RoleHierarchyCommand;
use AIArmada\FilamentAuthz\Console\RoleTemplateCommand;
use AIArmada\FilamentAuthz\Console\SnapshotCommand;
use Illuminate\Console\Command;
use ReflectionClass;

describe('Console Commands', function (): void {
    describe('GeneratePoliciesCommand', function (): void {
        it('extends Command', function (): void {
            expect(is_subclass_of(GeneratePoliciesCommand::class, Command::class))->toBeTrue();
        });

        it('has correct signature', function (): void {
            $reflection = new ReflectionClass(GeneratePoliciesCommand::class);
            $property = $reflection->getProperty('signature');

            $signature = $property->getDefaultValue();

            expect($signature)->toContain('authz:policies');
            expect($signature)->toContain('--type=');
            expect($signature)->toContain('--resource=*');
            expect($signature)->toContain('--model=*');
            expect($signature)->toContain('--panel=');
            expect($signature)->toContain('--namespace=');
            expect($signature)->toContain('--force');
            expect($signature)->toContain('--dry-run');
            expect($signature)->toContain('--interactive');
        });

        it('has correct description', function (): void {
            $reflection = new ReflectionClass(GeneratePoliciesCommand::class);
            $property = $reflection->getProperty('description');

            $description = $property->getDefaultValue();

            expect($description)->toContain('policies');
        });
    });

    describe('InstallTraitCommand', function (): void {
        it('extends Command', function (): void {
            expect(is_subclass_of(InstallTraitCommand::class, Command::class))->toBeTrue();
        });

        it('has correct signature', function (): void {
            $reflection = new ReflectionClass(InstallTraitCommand::class);
            $property = $reflection->getProperty('signature');

            $signature = $property->getDefaultValue();

            expect($signature)->toContain('authz:install-trait');
        });

        it('has correct description', function (): void {
            $reflection = new ReflectionClass(InstallTraitCommand::class);
            $property = $reflection->getProperty('description');

            $description = $property->getDefaultValue();

            expect($description)->toContain('trait');
        });
    });

    describe('RoleHierarchyCommand', function (): void {
        it('extends Command', function (): void {
            expect(is_subclass_of(RoleHierarchyCommand::class, Command::class))->toBeTrue();
        });

        it('has correct signature', function (): void {
            $reflection = new ReflectionClass(RoleHierarchyCommand::class);
            $property = $reflection->getProperty('signature');

            $signature = $property->getDefaultValue();

            expect($signature)->toContain('authz:roles-hierarchy');
            expect($signature)->toContain('{action?');
            expect($signature)->toContain('--role=');
            expect($signature)->toContain('--parent=');
        });

        it('has correct description', function (): void {
            $reflection = new ReflectionClass(RoleHierarchyCommand::class);
            $property = $reflection->getProperty('description');

            $description = $property->getDefaultValue();

            expect($description)->toContain('role');
            expect($description)->toContain('hierarchy');
        });
    });

    describe('RoleTemplateCommand', function (): void {
        it('extends Command', function (): void {
            expect(is_subclass_of(RoleTemplateCommand::class, Command::class))->toBeTrue();
        });

        it('has correct signature', function (): void {
            $reflection = new ReflectionClass(RoleTemplateCommand::class);
            $property = $reflection->getProperty('signature');

            $signature = $property->getDefaultValue();

            expect($signature)->toContain('authz:templates');
            expect($signature)->toContain('{action?');
            expect($signature)->toContain('--template=');
            expect($signature)->toContain('--role=');
        });

        it('has correct description', function (): void {
            $reflection = new ReflectionClass(RoleTemplateCommand::class);
            $property = $reflection->getProperty('description');

            $description = $property->getDefaultValue();

            expect($description)->toContain('template');
        });
    });

    describe('SnapshotCommand', function (): void {
        it('extends Command', function (): void {
            expect(is_subclass_of(SnapshotCommand::class, Command::class))->toBeTrue();
        });

        it('has correct signature', function (): void {
            $reflection = new ReflectionClass(SnapshotCommand::class);
            $property = $reflection->getProperty('signature');

            $signature = $property->getDefaultValue();

            expect($signature)->toContain('authz:snapshot');
        });

        it('has correct description', function (): void {
            $reflection = new ReflectionClass(SnapshotCommand::class);
            $property = $reflection->getProperty('description');

            $description = $property->getDefaultValue();

            expect($description)->toContain('snapshot');
        });
    });
});
