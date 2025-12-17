<?php

declare(strict_types=1);

namespace AIArmada\Chip\Tests\Feature;

use AIArmada\Chip\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

abstract class FeatureTestCase extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'chip.collect.api_key' => 'test_api_key',
            'chip.collect.brand_id' => 'test_brand_id',
            'chip.send.api_key' => 'test_api_key',
            'chip.send.api_secret' => 'test_api_secret',
            'chip.environment' => 'sandbox',
            'chip.webhooks.verify_signature' => false,
            'chip.logging.channel' => 'single',
            'logging.channels.single.driver' => 'single',
            'logging.channels.single.path' => storage_path('logs/laravel.log'),
        ]);

        $this->createWebhookCallsTable();
    }

    protected function createWebhookCallsTable(): void
    {
        if (! Schema::hasTable('webhook_calls')) {
            Schema::create('webhook_calls', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('url')->nullable();
                $table->json('headers')->nullable();
                $table->json('payload')->nullable();
                $table->text('exception')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function createWebhookPayload(string $event, array $data, array $overrides = []): array
    {
        return array_merge([
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function signWebhookPayload(array $payload): string
    {
        return base64_encode(hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), 'test_secret', true));
    }
}
