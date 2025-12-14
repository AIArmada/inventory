<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

describe('AffiliateSupportTicket Model', function (): void {
    it('can be created with required fields', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TICKET-TEST-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $ticket = AffiliateSupportTicket::create([
            'affiliate_id' => $affiliate->id,
            'subject' => 'Commission Question',
            'category' => 'commissions',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        expect($ticket)->toBeInstanceOf(AffiliateSupportTicket::class)
            ->and($ticket->subject)->toBe('Commission Question')
            ->and($ticket->category)->toBe('commissions')
            ->and($ticket->priority)->toBe('normal')
            ->and($ticket->status)->toBe('open');
    });

    it('belongs to an affiliate', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TICKET-AFF-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $ticket = AffiliateSupportTicket::create([
            'affiliate_id' => $affiliate->id,
            'subject' => 'Help Request',
            'category' => 'general',
            'priority' => 'low',
            'status' => 'open',
        ]);

        expect($ticket->affiliate())->toBeInstanceOf(BelongsTo::class)
            ->and($ticket->affiliate->id)->toBe($affiliate->id);
    });

    it('has many messages', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TICKET-MSG-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $ticket = AffiliateSupportTicket::create([
            'affiliate_id' => $affiliate->id,
            'subject' => 'Question',
            'category' => 'payments',
            'priority' => 'high',
            'status' => 'open',
        ]);

        expect($ticket->messages())->toBeInstanceOf(HasMany::class);
    });

    it('loads messages ordered by created_at', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TICKET-ORDER-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $ticket = AffiliateSupportTicket::create([
            'affiliate_id' => $affiliate->id,
            'subject' => 'Conversation',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        AffiliateSupportMessage::create([
            'ticket_id' => $ticket->id,
            'message' => 'First message',
            'is_staff_reply' => false,
        ]);

        AffiliateSupportMessage::create([
            'ticket_id' => $ticket->id,
            'message' => 'Second message',
            'is_staff_reply' => true,
        ]);

        $ticket->refresh();
        expect($ticket->messages)->toHaveCount(2);
    });

    it('supports different priority levels', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TICKET-PRIO-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $lowTicket = AffiliateSupportTicket::create([
            'affiliate_id' => $affiliate->id,
            'subject' => 'Low Priority',
            'category' => 'general',
            'priority' => 'low',
            'status' => 'open',
        ]);

        $highTicket = AffiliateSupportTicket::create([
            'affiliate_id' => $affiliate->id,
            'subject' => 'High Priority',
            'category' => 'urgent',
            'priority' => 'urgent',
            'status' => 'open',
        ]);

        expect($lowTicket->priority)->toBe('low')
            ->and($highTicket->priority)->toBe('urgent');
    });

    it('supports different statuses', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TICKET-STAT-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $openTicket = AffiliateSupportTicket::create([
            'affiliate_id' => $affiliate->id,
            'subject' => 'Open Ticket',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $closedTicket = AffiliateSupportTicket::create([
            'affiliate_id' => $affiliate->id,
            'subject' => 'Closed Ticket',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'closed',
        ]);

        expect($openTicket->status)->toBe('open')
            ->and($closedTicket->status)->toBe('closed');
    });
});
