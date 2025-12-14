<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;

describe('AffiliateSupportTicket Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'SUPPORT' . uniqid(),
            'name' => 'Support Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('can be created with required fields', function (): void {
        $ticket = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Need help with tracking',
            'category' => 'technical',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        expect($ticket)->toBeInstanceOf(AffiliateSupportTicket::class);
        expect($ticket->subject)->toBe('Need help with tracking');
        expect($ticket->category)->toBe('technical');
        expect($ticket->priority)->toBe('normal');
        expect($ticket->status)->toBe('open');
    });

    test('belongs to affiliate', function (): void {
        $ticket = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Question about commissions',
            'category' => 'billing',
            'priority' => 'high',
            'status' => 'open',
        ]);

        expect($ticket->affiliate)->toBeInstanceOf(Affiliate::class);
        expect($ticket->affiliate->id)->toBe($this->affiliate->id);
    });

    test('has many messages', function (): void {
        $ticket = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Discussion topic',
            'category' => 'general',
            'priority' => 'low',
            'status' => 'open',
        ]);

        AffiliateSupportMessage::create([
            'ticket_id' => $ticket->id,
            'affiliate_id' => $this->affiliate->id,
            'message' => 'First message',
            'is_staff_reply' => false,
        ]);

        AffiliateSupportMessage::create([
            'ticket_id' => $ticket->id,
            'staff_id' => 'agent@example.com',
            'message' => 'Response from support',
            'is_staff_reply' => true,
        ]);

        expect($ticket->messages)->toHaveCount(2);
    });

    test('messages are ordered by created_at', function (): void {
        $ticket = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Message ordering test',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $firstMessage = AffiliateSupportMessage::create([
            'ticket_id' => $ticket->id,
            'affiliate_id' => $this->affiliate->id,
            'message' => 'First message',
            'is_staff_reply' => false,
            'created_at' => now()->subHour(),
        ]);

        $secondMessage = AffiliateSupportMessage::create([
            'ticket_id' => $ticket->id,
            'staff_id' => 'agent@example.com',
            'message' => 'Second message',
            'is_staff_reply' => true,
            'created_at' => now(),
        ]);

        $messages = $ticket->messages;
        expect($messages->first()->id)->toBe($firstMessage->id);
        expect($messages->last()->id)->toBe($secondMessage->id);
    });

    test('can have various priority levels', function (): void {
        $lowPriority = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Low priority issue',
            'category' => 'general',
            'priority' => 'low',
            'status' => 'open',
        ]);

        $urgentPriority = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Urgent issue',
            'category' => 'billing',
            'priority' => 'urgent',
            'status' => 'open',
        ]);

        expect($lowPriority->priority)->toBe('low');
        expect($urgentPriority->priority)->toBe('urgent');
    });

    test('can have various status values', function (): void {
        $openTicket = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Open ticket',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $closedTicket = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Closed ticket',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'closed',
        ]);

        expect($openTicket->status)->toBe('open');
        expect($closedTicket->status)->toBe('closed');
    });

    test('can have different categories', function (): void {
        $technical = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Technical issue',
            'category' => 'technical',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $billing = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Billing question',
            'category' => 'billing',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        expect($technical->category)->toBe('technical');
        expect($billing->category)->toBe('billing');
    });
});
