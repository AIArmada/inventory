<?php

declare(strict_types=1);

use AIArmada\Customers\CustomersServiceProvider;
use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerGroup;
use AIArmada\Customers\Models\CustomerNote;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Policies\AddressPolicy;
use AIArmada\Customers\Policies\CustomerGroupPolicy;
use AIArmada\Customers\Policies\CustomerNotePolicy;
use AIArmada\Customers\Policies\CustomerPolicy;
use AIArmada\Customers\Policies\SegmentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

describe('CustomersServiceProvider', function (): void {
    describe('Instantiation', function (): void {
        it('can be instantiated', function (): void {
            $provider = new CustomersServiceProvider(app());

            expect($provider)->toBeInstanceOf(ServiceProvider::class);
        });
    });

    describe('register Method', function (): void {
        it('merges config', function (): void {
            $provider = new CustomersServiceProvider(app());
            $provider->register();

            expect(config('customers'))->toBeArray();
        });
    });

    describe('boot Method', function (): void {
        it('can boot without errors', function (): void {
            $provider = new CustomersServiceProvider(app());
            $provider->register();
            $provider->boot();

            expect(true)->toBeTrue();
        });

        it('loads translations', function (): void {
            $provider = new CustomersServiceProvider(app());
            $provider->register();
            $provider->boot();

            $translator = app('translator');
            $namespaces = $translator->getLoader()->namespaces();

            expect($namespaces)->toHaveKey('customers');
        });

        it('registers policies for all customers models', function (): void {
            $provider = new CustomersServiceProvider(app());
            $provider->register();
            $provider->boot();

            expect(Gate::getPolicyFor(Customer::class))->toBeInstanceOf(CustomerPolicy::class);
            expect(Gate::getPolicyFor(Segment::class))->toBeInstanceOf(SegmentPolicy::class);
            expect(Gate::getPolicyFor(Address::class))->toBeInstanceOf(AddressPolicy::class);
            expect(Gate::getPolicyFor(CustomerNote::class))->toBeInstanceOf(CustomerNotePolicy::class);
            expect(Gate::getPolicyFor(CustomerGroup::class))->toBeInstanceOf(CustomerGroupPolicy::class);
        });
    });

    describe('Publishing', function (): void {
        it('has config publish paths defined', function (): void {
            $provider = new CustomersServiceProvider(app());
            $provider->register();
            $provider->boot();

            $paths = ServiceProvider::pathsToPublish(CustomersServiceProvider::class, 'customers-config');

            expect($paths)->toBeArray()
                ->and(count($paths))->toBeGreaterThanOrEqual(1);
        });

        it('has migrations publish paths defined', function (): void {
            $provider = new CustomersServiceProvider(app());
            $provider->register();
            $provider->boot();

            $paths = ServiceProvider::pathsToPublish(CustomersServiceProvider::class, 'customers-migrations');

            expect($paths)->toBeArray()
                ->and(count($paths))->toBeGreaterThanOrEqual(1);
        });
    });
});
