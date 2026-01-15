<?php

declare(strict_types=1);

use AIArmada\Chip\Commands\AggregateMetricsCommand;
use AIArmada\Chip\Commands\CleanWebhooksCommand;
use AIArmada\Chip\Commands\RetryWebhooksCommand;

/**
 * These tests verify command signatures, descriptions, and basic instantiation.
 * Full integration tests for command execution require database and service bindings.
 */
describe('Command classes structure', function (): void {
    describe('AggregateMetricsCommand', function (): void {
        it('has correct name', function (): void {
            $command = new AggregateMetricsCommand;
            expect($command->getName())->toBe('chip:aggregate-metrics');
        });

        it('has description', function (): void {
            $command = new AggregateMetricsCommand;
            expect($command->getDescription())->not()->toBeEmpty();
        });

        it('has date option', function (): void {
            $command = new AggregateMetricsCommand;
            expect($command->getDefinition()->hasOption('date'))->toBeTrue();
        });

        it('has from option', function (): void {
            $command = new AggregateMetricsCommand;
            expect($command->getDefinition()->hasOption('from'))->toBeTrue();
        });

        it('has to option', function (): void {
            $command = new AggregateMetricsCommand;
            expect($command->getDefinition()->hasOption('to'))->toBeTrue();
        });
    });

    describe('CleanWebhooksCommand', function (): void {
        it('has correct name', function (): void {
            $command = new CleanWebhooksCommand;
            expect($command->getName())->toBe('chip:clean-webhooks');
        });

        it('has description', function (): void {
            $command = new CleanWebhooksCommand;
            expect($command->getDescription())->not()->toBeEmpty();
        });

        it('has days option', function (): void {
            $command = new CleanWebhooksCommand;
            expect($command->getDefinition()->hasOption('days'))->toBeTrue();
        });

        it('has status option', function (): void {
            $command = new CleanWebhooksCommand;
            expect($command->getDefinition()->hasOption('status'))->toBeTrue();
        });

        it('has dry-run option', function (): void {
            $command = new CleanWebhooksCommand;
            expect($command->getDefinition()->hasOption('dry-run'))->toBeTrue();
        });

        it('days option defaults to 30', function (): void {
            $command = new CleanWebhooksCommand;
            $option = $command->getDefinition()->getOption('days');
            expect($option->getDefault())->toBe('30');
        });

        it('status option defaults to processed', function (): void {
            $command = new CleanWebhooksCommand;
            $option = $command->getDefinition()->getOption('status');
            expect($option->getDefault())->toBe('processed');
        });
    });

    describe('RetryWebhooksCommand', function (): void {
        it('has correct name', function (): void {
            $command = new RetryWebhooksCommand;
            expect($command->getName())->toBe('chip:retry-webhooks');
        });

        it('has description', function (): void {
            $command = new RetryWebhooksCommand;
            expect($command->getDescription())->not()->toBeEmpty();
        });

        it('has limit option', function (): void {
            $command = new RetryWebhooksCommand;
            expect($command->getDefinition()->hasOption('limit'))->toBeTrue();
        });

        it('has dry-run option', function (): void {
            $command = new RetryWebhooksCommand;
            expect($command->getDefinition()->hasOption('dry-run'))->toBeTrue();
        });

        it('limit option defaults to 100', function (): void {
            $command = new RetryWebhooksCommand;
            $option = $command->getDefinition()->getOption('limit');
            expect($option->getDefault())->toBe('100');
        });
    });
});
