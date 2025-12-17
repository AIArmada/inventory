<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\RecoveryAttempt;
use AIArmada\FilamentCart\Models\RecoveryCampaign;
use AIArmada\FilamentCart\Models\RecoveryTemplate;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('RecoveryAttempt', function (): void {
    beforeEach(function (): void {
        $this->campaign = RecoveryCampaign::create([
            'name' => 'Test Campaign',
            'status' => 'active',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
        ]);

        $this->template = RecoveryTemplate::create([
            'name' => 'Test Template',
            'type' => 'email',
            'status' => 'active',
        ]);

        $this->cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-123',
            'email' => 'customer@example.com',
            'subtotal' => 15000,
        ]);
    });

    it('can be created with required attributes', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => $this->template->id,
            'recipient_email' => 'test@example.com',
            'channel' => 'email',
            'status' => 'scheduled',
            'attempt_number' => 1,
        ]);

        expect($attempt)->toBeInstanceOf(RecoveryAttempt::class);
        expect($attempt->id)->not->toBeNull();
        expect($attempt->channel)->toBe('email');
        expect($attempt->status)->toBe('scheduled');
    });

    it('returns table name from config', function (): void {
        $attempt = new RecoveryAttempt();
        $tableName = $attempt->getTable();

        expect($tableName)->toContain('recovery_attempts');
    });

    it('belongs to campaign', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'scheduled',
            'attempt_number' => 1,
        ]);

        expect($attempt->campaign)->toBeInstanceOf(RecoveryCampaign::class);
        expect($attempt->campaign->id)->toBe($this->campaign->id);
    });

    it('belongs to cart', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'scheduled',
            'attempt_number' => 1,
        ]);

        expect($attempt->cart)->toBeInstanceOf(Cart::class);
        expect($attempt->cart->id)->toBe($this->cart->id);
    });

    it('belongs to template', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'template_id' => $this->template->id,
            'channel' => 'email',
            'status' => 'scheduled',
            'attempt_number' => 1,
        ]);

        expect($attempt->template)->toBeInstanceOf(RecoveryTemplate::class);
    });

    it('checks if scheduled', function (): void {
        $scheduled = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'scheduled',
            'attempt_number' => 1,
        ]);

        $sent = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'sent',
            'attempt_number' => 2,
        ]);

        expect($scheduled->isScheduled())->toBeTrue();
        expect($sent->isScheduled())->toBeFalse();
    });

    it('checks if sent', function (): void {
        $sent = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'sent',
            'attempt_number' => 1,
        ]);

        expect($sent->isSent())->toBeTrue();
    });

    it('checks if opened', function (): void {
        $opened = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'opened',
            'attempt_number' => 1,
        ]);

        expect($opened->isOpened())->toBeTrue();
    });

    it('checks if clicked', function (): void {
        $clicked = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'clicked',
            'attempt_number' => 1,
        ]);

        expect($clicked->isClicked())->toBeTrue();
    });

    it('checks if converted', function (): void {
        $converted = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'converted',
            'attempt_number' => 1,
        ]);

        expect($converted->isConverted())->toBeTrue();
    });

    it('checks if failed', function (): void {
        $failed = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'failed',
            'attempt_number' => 1,
        ]);

        expect($failed->isFailed())->toBeTrue();
    });

    it('can mark as sent', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'queued',
            'attempt_number' => 1,
        ]);

        $attempt->markAsSent('msg-123');
        $attempt->refresh();

        expect($attempt->status)->toBe('sent');
        expect($attempt->sent_at)->not->toBeNull();
        expect($attempt->message_id)->toBe('msg-123');
    });

    it('can mark as opened', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'sent',
            'attempt_number' => 1,
        ]);

        $attempt->markAsOpened();
        $attempt->refresh();

        expect($attempt->status)->toBe('opened');
        expect($attempt->opened_at)->not->toBeNull();
    });

    it('can mark as clicked', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'sent',
            'attempt_number' => 1,
        ]);

        $attempt->markAsClicked();
        $attempt->refresh();

        expect($attempt->status)->toBe('clicked');
        expect($attempt->clicked_at)->not->toBeNull();
        expect($attempt->opened_at)->not->toBeNull(); // opened is implied
    });

    it('can mark as converted', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'sent',
            'attempt_number' => 1,
        ]);

        $attempt->markAsConverted();
        $attempt->refresh();

        expect($attempt->status)->toBe('converted');
        expect($attempt->converted_at)->not->toBeNull();
    });

    it('can mark as failed', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'queued',
            'attempt_number' => 1,
        ]);

        $attempt->markAsFailed('Connection timeout');
        $attempt->refresh();

        expect($attempt->status)->toBe('failed');
        expect($attempt->failed_at)->not->toBeNull();
        expect($attempt->failure_reason)->toBe('Connection timeout');
    });

    it('does not re-open if already opened', function (): void {
        $attempt = RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'opened',
            'attempt_number' => 1,
            'opened_at' => now()->subHour(),
        ]);

        $originalOpenedAt = $attempt->opened_at;
        $attempt->markAsOpened();
        $attempt->refresh();

        expect($attempt->opened_at->eq($originalOpenedAt))->toBeTrue();
    });
});
