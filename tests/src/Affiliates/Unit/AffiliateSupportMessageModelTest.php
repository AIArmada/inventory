<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('AffiliateSupportMessage Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'SUPPORT-MSG-' . uniqid(),
            'name' => 'Support Message Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $this->ticket = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Test Ticket',
            'status' => 'open',
            'priority' => 'normal',
        ]);
    });

    it('can be created with required fields', function (): void {
        $message = AffiliateSupportMessage::create([
            'ticket_id' => $this->ticket->id,
            'message' => 'This is a test message.',
            'is_staff_reply' => false,
        ]);

        expect($message)->toBeInstanceOf(AffiliateSupportMessage::class)
            ->and($message->message)->toBe('This is a test message.')
            ->and($message->is_staff_reply)->toBeFalse();
    });

    it('belongs to ticket', function (): void {
        $message = AffiliateSupportMessage::create([
            'ticket_id' => $this->ticket->id,
            'message' => 'Test message',
            'is_staff_reply' => false,
        ]);

        expect($message->ticket())->toBeInstanceOf(BelongsTo::class)
            ->and($message->ticket->id)->toBe($this->ticket->id);
    });

    it('belongs to affiliate', function (): void {
        $message = AffiliateSupportMessage::create([
            'ticket_id' => $this->ticket->id,
            'affiliate_id' => $this->affiliate->id,
            'message' => 'Test message from affiliate',
            'is_staff_reply' => false,
        ]);

        expect($message->affiliate())->toBeInstanceOf(BelongsTo::class)
            ->and($message->affiliate->id)->toBe($this->affiliate->id);
    });

    it('can be created as staff reply', function (): void {
        $message = AffiliateSupportMessage::create([
            'ticket_id' => $this->ticket->id,
            'staff_id' => 'staff_123',
            'message' => 'Staff response',
            'is_staff_reply' => true,
        ]);

        expect($message->is_staff_reply)->toBeTrue()
            ->and($message->staff_id)->toBe('staff_123');
    });

    it('casts is_staff_reply as boolean', function (): void {
        $message = AffiliateSupportMessage::create([
            'ticket_id' => $this->ticket->id,
            'message' => 'Test message',
            'is_staff_reply' => 1,
        ]);

        expect($message->is_staff_reply)->toBeBool();
    });

    it('allows affiliate_id to be null for staff messages', function (): void {
        $message = AffiliateSupportMessage::create([
            'ticket_id' => $this->ticket->id,
            'staff_id' => 'staff_456',
            'message' => 'Staff only message',
            'is_staff_reply' => true,
        ]);

        expect($message->affiliate_id)->toBeNull();
    });

    it('allows staff_id to be null for affiliate messages', function (): void {
        $message = AffiliateSupportMessage::create([
            'ticket_id' => $this->ticket->id,
            'affiliate_id' => $this->affiliate->id,
            'message' => 'Affiliate message',
            'is_staff_reply' => false,
        ]);

        expect($message->staff_id)->toBeNull();
    });
});
