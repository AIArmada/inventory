<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerScopeConfigurable;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('owner_scope_fixtures');
    Schema::create('owner_scope_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
        $table->string('label');
        $table->timestamps();
    });
});

afterEach(function (): void {
    OwnerContext::clearOverride();
});

it('applies the owner scope and supports explicit opt-out', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-scope@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-scope@example.com',
        'password' => 'secret',
    ]);

    OwnerScopedFixture::query()->create([
        'label' => 'owner-a',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    OwnerScopedFixture::query()->create([
        'label' => 'owner-b',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    OwnerScopedFixture::query()->create([
        'label' => 'global',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    OwnerContext::override($ownerA);

    $scoped = OwnerScopedFixture::query()
        ->orderBy('label')
        ->pluck('label')
        ->all();

    expect($scoped)->toBe(['owner-a']);

    $unscoped = OwnerScopedFixture::query()
        ->withoutOwnerScope()
        ->orderBy('label')
        ->pluck('label')
        ->all();

    expect($unscoped)->toBe(['global', 'owner-a', 'owner-b']);

    OwnerContext::override($ownerB);

    $forOwner = OwnerScopedFixture::query()
        ->withoutOwnerScope()
        ->forOwner()
        ->orderBy('label')
        ->pluck('label')
        ->all();

    expect($forOwner)->toBe(['owner-b']);
});

it('scopes query builder owner columns with optional include-global', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-query@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-query@example.com',
        'password' => 'secret',
    ]);

    DB::table('owner_scope_fixtures')->insert([
        [
            'label' => 'owner-a',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ],
        [
            'label' => 'owner-b',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
        ],
        [
            'label' => 'global',
            'owner_type' => null,
            'owner_id' => null,
        ],
    ]);

    $scoped = OwnerQuery::applyToQueryBuilder(DB::table('owner_scope_fixtures'), $ownerB, true)
        ->orderBy('label')
        ->pluck('label')
        ->all();

    expect($scoped)->toBe(['global', 'owner-b']);
});

it('defaults to excluding global rows for models without explicit config', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-default-include-global@example.com',
        'password' => 'secret',
    ]);

    OwnerScopedNoConfigFixture::query()->create([
        'label' => 'owner-a',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    OwnerScopedNoConfigFixture::query()->create([
        'label' => 'global',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $scopedDefault = OwnerScopedNoConfigFixture::query()
        ->withoutOwnerScope()
        ->forOwner($ownerA)
        ->orderBy('label')
        ->pluck('label')
        ->all();

    expect($scopedDefault)->toBe(['owner-a']);

    $scopedWithGlobal = OwnerScopedNoConfigFixture::query()
        ->withoutOwnerScope()
        ->forOwner($ownerA, true)
        ->orderBy('label')
        ->pluck('label')
        ->all();

    expect($scopedWithGlobal)->toBe(['global', 'owner-a']);
});

final class OwnerScopedFixture extends Model implements OwnerScopeConfigurable
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_scope_fixtures';
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: true, includeGlobal: false);
    }
}

final class OwnerScopedNoConfigFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_scope_fixtures';
    }
}
