<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport;

use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Support Service Provider
 *
 * Foundation service provider for all AIArmada Commerce packages.
 * Provides core helper methods, utilities, and base functionality.
 */
final class SupportServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('commerce-support')
            ->hasConfigFile('commerce-support')
            ->hasCommands([
                Commands\SetupCommand::class,
                Commands\BoostUpdateCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->registerOwnerResolver();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [];
    }

    private function registerOwnerResolver(): void
    {
        if ($this->app->bound(OwnerResolverInterface::class)) {
            return;
        }

        /** @var class-string $resolverClass */
        $resolverClass = (string) config('commerce-support.owner.resolver', NullOwnerResolver::class);

        if ($resolverClass === '' || ! class_exists($resolverClass)) {
            throw new InvalidArgumentException(sprintf('Invalid owner resolver class: %s', $resolverClass));
        }

        if (! is_a($resolverClass, OwnerResolverInterface::class, true)) {
            throw new InvalidArgumentException(
                sprintf('%s must implement %s', $resolverClass, OwnerResolverInterface::class)
            );
        }

        $this->app->singleton(OwnerResolverInterface::class, function ($app): OwnerResolverInterface {
            /** @var class-string<OwnerResolverInterface> $resolverClass */
            $resolverClass = (string) config('commerce-support.owner.resolver', NullOwnerResolver::class);

            $resolver = $app->make($resolverClass);

            if (! $resolver instanceof OwnerResolverInterface) {
                throw new InvalidArgumentException(
                    sprintf('%s must implement %s', $resolverClass, OwnerResolverInterface::class)
                );
            }

            return $resolver;
        });
    }
}
