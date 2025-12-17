<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Events\CartRecovered;
use AIArmada\FilamentCart\Events\RecoveryAttemptClicked;
use AIArmada\FilamentCart\Events\RecoveryAttemptOpened;
use AIArmada\FilamentCart\Events\RecoveryAttemptSent;
use AIArmada\FilamentCart\Models\RecoveryAttempt;

describe('CartRecovered', function (): void {
    it('can be constructed with attempt and order value', function (): void {
        // Create a mock attempt
        $attempt = new RecoveryAttempt();
        $attempt->id = 'attempt-123';

        $event = new CartRecovered(
            attempt: $attempt,
            orderValueCents: 15000,
        );

        expect($event->attempt)->toBe($attempt);
        expect($event->orderValueCents)->toBe(15000);
    });
});

describe('RecoveryAttemptSent', function (): void {
    it('can be constructed with attempt', function (): void {
        $attempt = new RecoveryAttempt();
        $attempt->id = 'attempt-456';

        $event = new RecoveryAttemptSent(attempt: $attempt);

        expect($event->attempt)->toBe($attempt);
    });
});

describe('RecoveryAttemptOpened', function (): void {
    it('can be constructed with attempt', function (): void {
        $attempt = new RecoveryAttempt();
        $attempt->id = 'attempt-789';

        $event = new RecoveryAttemptOpened(attempt: $attempt);

        expect($event->attempt)->toBe($attempt);
    });
});

describe('RecoveryAttemptClicked', function (): void {
    it('can be constructed with attempt', function (): void {
        $attempt = new RecoveryAttempt();
        $attempt->id = 'attempt-abc';

        $event = new RecoveryAttemptClicked(attempt: $attempt);

        expect($event->attempt)->toBe($attempt);
    });
});
