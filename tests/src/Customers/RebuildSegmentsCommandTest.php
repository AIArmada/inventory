<?php

declare(strict_types=1);

use AIArmada\Customers\Console\Commands\RebuildSegmentsCommand;
use AIArmada\Customers\Models\Segment;

describe('RebuildSegmentsCommand', function (): void {
    describe('Instantiation', function (): void {
        it('can be instantiated', function (): void {
            $command = new RebuildSegmentsCommand();

            expect($command)->toBeInstanceOf(RebuildSegmentsCommand::class);
        });

        it('has correct signature', function (): void {
            $command = new RebuildSegmentsCommand();

            expect($command->getName())->toBe('customers:rebuild-segments');
        });

        it('has correct description', function (): void {
            $command = new RebuildSegmentsCommand();

            expect($command->getDescription())->toBe('Rebuild automatic customer segment memberships');
        });

        it('has segment option defined', function (): void {
            $command = new RebuildSegmentsCommand();
            $definition = $command->getDefinition();

            expect($definition->hasOption('segment'))->toBeTrue();
        });

        it('has dry-run option defined', function (): void {
            $command = new RebuildSegmentsCommand();
            $definition = $command->getDefinition();

            expect($definition->hasOption('dry-run'))->toBeTrue();
        });
    });

    describe('Handle Method via Artisan', function (): void {
        it('handles empty segments', function (): void {
            // Ensure no automatic active segments exist
            Segment::query()->where('is_automatic', true)->where('is_active', true)->delete();

            // Register command
            $this->app->make(Illuminate\Contracts\Console\Kernel::class)
                ->registerCommand(new RebuildSegmentsCommand());

            $this->artisan('customers:rebuild-segments')
                ->assertExitCode(0);
        });

        it('rebuilds all automatic segments', function (): void {
            Segment::create([
                'name' => 'Rebuild All Test ' . uniqid(),
                'slug' => 'rebuild-all-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'conditions' => [],
            ]);

            $this->app->make(Illuminate\Contracts\Console\Kernel::class)
                ->registerCommand(new RebuildSegmentsCommand());

            $this->artisan('customers:rebuild-segments')
                ->assertExitCode(0);
        });

        it('handles single segment option', function (): void {
            $segment = Segment::create([
                'name' => 'Single Test ' . uniqid(),
                'slug' => 'single-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'conditions' => [],
            ]);

            $this->app->make(Illuminate\Contracts\Console\Kernel::class)
                ->registerCommand(new RebuildSegmentsCommand());

            $this->artisan('customers:rebuild-segments', ['--segment' => $segment->id])
                ->assertExitCode(0);
        });

        it('handles non-existent segment', function (): void {
            $this->app->make(Illuminate\Contracts\Console\Kernel::class)
                ->registerCommand(new RebuildSegmentsCommand());

            $this->artisan('customers:rebuild-segments', ['--segment' => 'non-existent'])
                ->assertExitCode(1); // FAILURE
        });

        it('handles manual segment', function (): void {
            $segment = Segment::create([
                'name' => 'Manual Test ' . uniqid(),
                'slug' => 'manual-' . uniqid(),
                'is_active' => true,
                'is_automatic' => false,
            ]);

            $this->app->make(Illuminate\Contracts\Console\Kernel::class)
                ->registerCommand(new RebuildSegmentsCommand());

            $this->artisan('customers:rebuild-segments', ['--segment' => $segment->id])
                ->assertExitCode(0);
        });

        it('handles dry-run mode', function (): void {
            Segment::create([
                'name' => 'Dry Run Test ' . uniqid(),
                'slug' => 'dry-run-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'conditions' => [],
            ]);

            $this->app->make(Illuminate\Contracts\Console\Kernel::class)
                ->registerCommand(new RebuildSegmentsCommand());

            $this->artisan('customers:rebuild-segments', ['--dry-run' => true])
                ->assertExitCode(0);
        });

        it('handles dry-run mode for single segment', function (): void {
            $segment = Segment::create([
                'name' => 'Single Dry Run ' . uniqid(),
                'slug' => 'single-dry-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'conditions' => [],
            ]);

            $this->app->make(Illuminate\Contracts\Console\Kernel::class)
                ->registerCommand(new RebuildSegmentsCommand());

            $this->artisan('customers:rebuild-segments', [
                '--segment' => $segment->id,
                '--dry-run' => true,
            ])->assertExitCode(0);
        });
    });
});
