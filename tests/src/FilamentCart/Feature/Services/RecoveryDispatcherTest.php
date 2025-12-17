<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\RecoveryAttempt;
use AIArmada\FilamentCart\Models\RecoveryCampaign;
use AIArmada\FilamentCart\Models\RecoveryTemplate;
use AIArmada\FilamentCart\Services\RecoveryDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
    Mail::fake();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('RecoveryDispatcher', function (): void {
    beforeEach(function (): void {
        $this->dispatcher = new RecoveryDispatcher();

        // Define required routes for testing
        \Illuminate\Support\Facades\Route::get('/recovery/track/open/{attempt}', fn() => 'ok')
            ->name('cart.recovery.track.open');
        \Illuminate\Support\Facades\Route::get('/recovery/track/click/{attempt}', fn() => 'ok')
            ->name('cart.recovery.track.click');

        // Create a campaign
        $this->campaign = RecoveryCampaign::create([
            'name' => 'Test Campaign',
            'status' => 'active',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
        ]);

        // Create a template
        $this->template = RecoveryTemplate::create([
            'name' => 'Test Template',
            'type' => 'email',
            'status' => 'active',
            'email_subject' => 'Complete your order, {{customer_name}}!',
            'email_body_html' => '<p>Hi {{customer_name}}, come back and complete your order!</p>',
        ]);

        // Create a cart
        $this->cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-123',
            'email' => 'customer@example.com',
            'customer_name' => 'John Doe',
            'subtotal' => 15000,
            'items_count' => 3,
        ]);
    });

    it('can be instantiated', function (): void {
        expect($this->dispatcher)->toBeInstanceOf(RecoveryDispatcher::class);
    });

    it('returns false for non-queued attempts', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => $this->template->id,
            'recipient_email' => 'test@example.com',
            'channel' => 'email',
            'status' => 'scheduled', // Not queued
            'attempt_number' => 1,
        ]);

        $result = $this->dispatcher->dispatch($attempt);

        expect($result)->toBeFalse();
    });

    it('dispatches email for queued attempts', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => $this->template->id,
            'recipient_email' => 'customer@example.com',
            'recipient_name' => 'John Doe',
            'channel' => 'email',
            'status' => 'queued',
            'attempt_number' => 1,
            'cart_value_cents' => 15000,
            'cart_items_count' => 3,
        ]);

        $result = $this->dispatcher->dispatch($attempt);

        expect($result)->toBeTrue();
        expect($attempt->fresh()->status)->toBe('sent');
        // Mail::send doesn't use Mailable, so we verify the status instead.
    });

    it('fails dispatch without recipient email', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => $this->template->id,
            'recipient_email' => null,
            'channel' => 'email',
            'status' => 'queued',
            'attempt_number' => 1,
        ]);

        $result = $this->dispatcher->dispatchEmail($attempt, $this->template, []);

        expect($result)->toBeFalse();
    });

    it('dispatches SMS for queued attempts', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => $this->template->id,
            'recipient_phone' => '+1234567890',
            'channel' => 'sms',
            'status' => 'queued',
            'attempt_number' => 1,
        ]);

        $result = $this->dispatcher->dispatchSms($attempt, $this->template, []);

        expect($result)->toBeTrue();
        expect($attempt->fresh()->status)->toBe('sent');
    });

    it('fails SMS dispatch without phone', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => $this->template->id,
            'recipient_phone' => null,
            'channel' => 'sms',
            'status' => 'queued',
            'attempt_number' => 1,
        ]);

        $result = $this->dispatcher->dispatchSms($attempt, $this->template, []);

        expect($result)->toBeFalse();
    });

    it('dispatches push notification', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => $this->template->id,
            'channel' => 'push',
            'status' => 'queued',
            'attempt_number' => 1,
        ]);

        $result = $this->dispatcher->dispatchPush($attempt, $this->template, []);

        expect($result)->toBeTrue();
        expect($attempt->fresh()->status)->toBe('sent');
    });

    it('marks attempt as failed when template or cart is missing', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => null,
            'recipient_email' => 'customer@example.com',
            'channel' => 'email',
            'status' => 'queued',
            'attempt_number' => 1,
        ]);

        $result = $this->dispatcher->dispatch($attempt);

        expect($result)->toBeFalse();
        expect($attempt->fresh()->status)->toBe('failed');
        expect($attempt->fresh()->failure_reason)->toBe('Missing template or cart');
    });

    it('marks attempt as failed for unknown channels', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => $this->template->id,
            'recipient_email' => 'customer@example.com',
            'channel' => 'whatsapp',
            'status' => 'queued',
            'attempt_number' => 1,
        ]);

        $result = $this->dispatcher->dispatch($attempt);

        expect($result)->toBeFalse();
        expect($attempt->fresh()->status)->toBe('failed');
        expect($attempt->fresh()->failure_reason)->toContain('Unknown channel: whatsapp');
    });

    it('records open event', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => $this->template->id,
            'recipient_email' => 'test@example.com',
            'channel' => 'email',
            'status' => 'sent',
            'attempt_number' => 1,
        ]);

        $this->dispatcher->recordOpen($attempt);

        expect($attempt->fresh()->opened_at)->not->toBeNull();
    });

    it('records click event', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => $this->template->id,
            'recipient_email' => 'test@example.com',
            'channel' => 'email',
            'status' => 'sent',
            'attempt_number' => 1,
        ]);

        $this->dispatcher->recordClick($attempt);

        expect($attempt->fresh()->clicked_at)->not->toBeNull();
        expect($attempt->fresh()->opened_at)->not->toBeNull(); // Open is implied
    });

    it('records conversion event', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => $this->template->id,
            'recipient_email' => 'test@example.com',
            'channel' => 'email',
            'status' => 'sent',
            'attempt_number' => 1,
        ]);

        $this->dispatcher->recordConversion($attempt, 15000);

        expect($attempt->fresh()->converted_at)->not->toBeNull();
        expect($this->campaign->fresh()->total_recovered)->toBe(1);
    });
});
