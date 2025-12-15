<?php

declare(strict_types=1);

use AIArmada\Jnt\Exceptions\JntConfigurationException;

describe('JntConfigurationException', function (): void {
    it('creates exception for missing private key', function (): void {
        $exception = JntConfigurationException::missingPrivateKey();

        expect($exception)
            ->toBeInstanceOf(JntConfigurationException::class)
            ->getMessage()->toContain('private key is not configured')
            ->and($exception->configKey)->toBe('jnt.private_key');
    });

    it('creates exception for invalid private key', function (): void {
        $exception = JntConfigurationException::invalidPrivateKey();

        expect($exception)
            ->toBeInstanceOf(JntConfigurationException::class)
            ->getMessage()->toContain('private key format is invalid')
            ->and($exception->configKey)->toBe('jnt.private_key');
    });

    it('creates exception for missing API account', function (): void {
        $exception = JntConfigurationException::missingApiAccount();
        expect($exception)
            ->toBeInstanceOf(JntConfigurationException::class)
            ->getMessage()->toContain('API account is not configured')
            ->and($exception->configKey)->toBe('jnt.api_account');
    });

    it('creates exception for missing customer code', function (): void {
        $exception = JntConfigurationException::missingCustomerCode();

        expect($exception)
            ->toBeInstanceOf(JntConfigurationException::class)
            ->getMessage()->toContain('customer code is not configured')
            ->and($exception->configKey)->toBe('jnt.customer_code');
    });

    it('creates exception for missing password', function (): void {
        $exception = JntConfigurationException::missingPassword();

        expect($exception)
            ->toBeInstanceOf(JntConfigurationException::class)
            ->getMessage()->toContain('password is not configured')
            ->and($exception->configKey)->toBe('jnt.password');
    });

    it('creates exception for missing webhook URL', function (): void {
        $exception = JntConfigurationException::missingWebhookUrl();

        expect($exception)
            ->toBeInstanceOf(JntConfigurationException::class)
            ->getMessage()->toContain('webhook URL is not configured')
            ->and($exception->configKey)->toBe('jnt.webhook.url');
    });

    it('creates exception for invalid configuration value', function (): void {
        $exception = JntConfigurationException::invalidValue('jnt.timeout', 'Must be a positive integer');

        expect($exception)
            ->toBeInstanceOf(JntConfigurationException::class)
            ->getMessage()->toContain("Invalid configuration value for 'jnt.timeout'")
            ->getMessage()->toContain('Must be a positive integer')
            ->and($exception->configKey)->toBe('jnt.timeout');
    });

    it('creates exception for missing configuration key', function (): void {
        $exception = JntConfigurationException::missingKey('jnt.custom_setting');

        expect($exception)
            ->toBeInstanceOf(JntConfigurationException::class)
            ->getMessage()->toContain("Required configuration key 'jnt.custom_setting' is missing")
            ->and($exception->configKey)->toBe('jnt.custom_setting');
    });

    it('creates exception for invalid environment', function (): void {
        $exception = JntConfigurationException::invalidEnvironment('development');

        expect($exception)
            ->toBeInstanceOf(JntConfigurationException::class)
            ->getMessage()->toContain("Invalid J&T environment 'development'")
            ->getMessage()->toContain("Must be 'production' or 'testing'")
            ->and($exception->configKey)->toBe('jnt.environment');
    });
});
