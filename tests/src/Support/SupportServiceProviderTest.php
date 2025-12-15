<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Commands\SetupCommand;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\SupportServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelPackageTools\Package;

it('registers the commerce setup command', function (): void {
    $provider = new SupportServiceProvider(app());
    $package = new Package;

    $provider->configurePackage($package);

    expect($package->commands)->toContain(SetupCommand::class);
});

it('binds OwnerResolverInterface using commerce-support config', function (): void {
    putenv('COMMERCE_OWNER_RESOLVER=' . SupportTestOwnerResolver::class);
    $this->refreshApplication();

    expect(app(OwnerResolverInterface::class))
        ->toBeInstanceOf(SupportTestOwnerResolver::class);

    putenv('COMMERCE_OWNER_RESOLVER');
});

it('throws when configured owner resolver is invalid', function (): void {
    putenv('COMMERCE_OWNER_RESOLVER=' . stdClass::class);

    expect(fn () => $this->refreshApplication())
        ->toThrow(InvalidArgumentException::class);

    putenv('COMMERCE_OWNER_RESOLVER');
});

class SupportTestOwnerResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        return null;
    }
}
