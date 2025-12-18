<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentCart\Models\RecoveryCampaign;
use AIArmada\FilamentCart\Models\RecoveryTemplate;
use AIArmada\FilamentCart\Resources\RecoveryCampaignResource;
use AIArmada\FilamentCart\Resources\RecoveryTemplateResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

final class StaticOwnerResolverForRecovery implements OwnerResolverInterface
{
    public function __construct(private ?Model $owner)
    {
    }

    public function resolve(): ?Model
    {
        return $this->owner;
    }
}

it('scopes recovery resources by owner and blocks cross-tenant template references', function (): void {
    config()->set('filament-cart.owner.enabled', true);
    config()->set('filament-cart.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new StaticOwnerResolverForRecovery($ownerA));

    $templateA = RecoveryTemplate::query()->create([
        'name' => 'Template A',
        'type' => 'email',
        'status' => 'active',
        'is_default' => false,
    ]);

    $campaignA = RecoveryCampaign::query()->create([
        'name' => 'Campaign A',
        'status' => 'active',
        'trigger_type' => 'abandoned',
        'control_template_id' => $templateA->id,
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new StaticOwnerResolverForRecovery($ownerB));

    $templateB = RecoveryTemplate::query()->create([
        'name' => 'Template B',
        'type' => 'email',
        'status' => 'active',
        'is_default' => false,
    ]);

    RecoveryCampaign::query()->create([
        'name' => 'Campaign B',
        'status' => 'active',
        'trigger_type' => 'abandoned',
        'control_template_id' => $templateB->id,
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new StaticOwnerResolverForRecovery($ownerA));

    expect(RecoveryTemplateResource::getEloquentQuery()->count())->toBe(1);
    expect(RecoveryTemplateResource::getEloquentQuery()->first()?->id)->toBe($templateA->id);

    expect(RecoveryCampaignResource::getEloquentQuery()->count())->toBe(1);
    expect(RecoveryCampaignResource::getEloquentQuery()->first()?->id)->toBe($campaignA->id);

    expect(fn () => RecoveryCampaign::query()->create([
        'name' => 'Campaign Cross Tenant',
        'status' => 'active',
        'trigger_type' => 'abandoned',
        'control_template_id' => $templateB->id,
    ]))->toThrow(RuntimeException::class);
});
