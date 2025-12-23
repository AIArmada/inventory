<?php

declare(strict_types=1);

use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Testing\WebhookFactory;
use AIArmada\Chip\Http\Controllers\WebhookController;
use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Route;

it('assigns purchase owner from brand_id mapping when owner context is missing', function (): void {
    Route::post('/chip/webhook-test', [WebhookController::class, 'handle'])
        ->withoutMiddleware([VerifyWebhookSignature::class]);

    config()->set('chip.owner.enabled', true);

    $owner = User::query()->create([
        'name' => 'Webhook Owner',
        'email' => 'webhook-owner@example.com',
        'password' => 'secret',
    ]);

    config()->set('chip.owner.webhook_brand_id_map', [
        'brand-1' => [
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
        ],
    ]);

    OwnerContext::override(null);

    $payload = WebhookFactory::purchaseCreated([
        'brand_id' => 'brand-1',
    ]);

    $this->postJson('/chip/webhook-test', $payload)
        ->assertStatus(200);

    /** @var Purchase|null $purchase */
    $purchase = Purchase::query()->withoutOwnerScope()->where('id', $payload['id'])->first();

    expect($purchase)->not->toBeNull();
    expect($purchase?->owner_type)->toBe($owner->getMorphClass());
    expect($purchase?->owner_id)->toBe((string) $owner->getKey());
});

it('fails closed when owner scoping is enabled but brand_id has no owner mapping', function (): void {
    Route::post('/chip/webhook-test', [WebhookController::class, 'handle'])
        ->withoutMiddleware([VerifyWebhookSignature::class]);

    config()->set('chip.owner.enabled', true);
    config()->set('chip.owner.webhook_brand_id_map', []);

    OwnerContext::override(null);

    $payload = WebhookFactory::purchaseCreated([
        'brand_id' => 'brand-missing',
    ]);

    $this->postJson('/chip/webhook-test', $payload)
        ->assertStatus(500);

    expect(Purchase::query()->withoutOwnerScope()->where('id', $payload['id'])->exists())->toBeFalse();
});
