<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerRouteBinding;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('owner_route_binding_fixtures');
    Schema::create('owner_route_binding_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
        $table->string('label');
        $table->timestamps();
    });
});

afterEach(function (): void {
    OwnerContext::clearOverride();
});

it('resolves route-bound models inside the current owner scope', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-route-binding@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-route-binding@example.com',
        'password' => 'secret',
    ]);

    $ownerRecord = OwnerRouteBindingFixture::query()->create([
        'label' => 'owner-a',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $globalRecord = OwnerRouteBindingFixture::query()->create([
        'label' => 'global',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    OwnerContext::override($ownerA);

    $resolvedOwnerRecord = OwnerRouteBinding::resolve(OwnerRouteBindingFixture::class, (string) $ownerRecord->getKey());

    expect($resolvedOwnerRecord->label)->toBe('owner-a');

    expect(fn (): Model => OwnerRouteBinding::resolve(OwnerRouteBindingFixture::class, (string) $globalRecord->getKey()))
        ->toThrow(AuthorizationException::class);

    $resolvedGlobalRecord = OwnerRouteBinding::resolve(OwnerRouteBindingFixture::class, (string) $globalRecord->getKey(), true);

    expect($resolvedGlobalRecord->label)->toBe('global');

    OwnerContext::override($ownerB);

    expect(fn (): Model => OwnerRouteBinding::resolve(OwnerRouteBindingFixture::class, (string) $ownerRecord->getKey()))
        ->toThrow(AuthorizationException::class);
});

final class OwnerRouteBindingFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_route_binding_fixtures';
    }
}
