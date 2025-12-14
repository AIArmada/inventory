<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Http\Controllers\Portal\SupportController;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use Illuminate\Http\Request;

uses()->group('affiliates', 'unit');

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'SUPPORT-' . uniqid(),
        'name' => 'Test Affiliate',
        'contact_email' => 'test@example.com',
        'status' => AffiliateStatus::Active,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $this->controller = new SupportController;
});

describe('SupportController', function (): void {
    describe('index', function (): void {
        test('returns paginated list of support tickets', function (): void {
            // Create tickets
            AffiliateSupportTicket::create([
                'affiliate_id' => $this->affiliate->id,
                'subject' => 'Test Ticket 1',
                'category' => 'general',
                'priority' => 'normal',
                'status' => 'open',
            ]);

            AffiliateSupportTicket::create([
                'affiliate_id' => $this->affiliate->id,
                'subject' => 'Test Ticket 2',
                'category' => 'payout',
                'priority' => 'high',
                'status' => 'open',
            ]);

            $request = Request::create('/affiliate/portal/support', 'GET');
            $request->setUserResolver(fn () => $this->affiliate);

            $response = $this->controller->index($request);

            expect($response->getStatusCode())->toBe(200);

            $data = $response->getData(true);
            expect($data)->toHaveKey('tickets');
        });

        test('returns empty list when no tickets exist', function (): void {
            $request = Request::create('/affiliate/portal/support', 'GET');
            $request->setUserResolver(fn () => $this->affiliate);

            $response = $this->controller->index($request);

            $data = $response->getData(true);
            expect($data['tickets']['data'])->toBeEmpty();
        });

        test('returns only affiliate own tickets', function (): void {
            // Create ticket for this affiliate
            AffiliateSupportTicket::create([
                'affiliate_id' => $this->affiliate->id,
                'subject' => 'My Ticket',
                'category' => 'general',
                'priority' => 'normal',
                'status' => 'open',
            ]);

            // Create ticket for another affiliate
            $otherAffiliate = Affiliate::create([
                'code' => 'OTHER-' . uniqid(),
                'name' => 'Other Affiliate',
                'contact_email' => 'other@example.com',
                'status' => AffiliateStatus::Active,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            AffiliateSupportTicket::create([
                'affiliate_id' => $otherAffiliate->id,
                'subject' => 'Other Ticket',
                'category' => 'general',
                'priority' => 'normal',
                'status' => 'open',
            ]);

            $request = Request::create('/affiliate/portal/support', 'GET');
            $request->setUserResolver(fn () => $this->affiliate);

            $response = $this->controller->index($request);

            $data = $response->getData(true);
            expect($data['tickets']['total'])->toBe(1);
        });
    });

    describe('store', function (): void {
        test('creates new support ticket', function (): void {
            $request = Request::create('/affiliate/portal/support', 'POST', [
                'subject' => 'Need help with payouts',
                'message' => 'I have not received my payout for this month.',
                'category' => 'payout',
                'priority' => 'high',
            ]);
            $request->setUserResolver(fn () => $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $response = $this->controller->store($request);

            expect($response->getStatusCode())->toBe(201);

            $data = $response->getData(true);
            expect($data['ticket']['subject'])->toBe('Need help with payouts');
            expect($data['ticket']['category'])->toBe('payout');
            expect($data['ticket']['priority'])->toBe('high');
            expect($data['ticket']['status'])->toBe('open');
            expect($data['message'])->toBe('Support ticket created successfully.');
        });

        test('creates ticket with default category and priority', function (): void {
            $request = Request::create('/affiliate/portal/support', 'POST', [
                'subject' => 'General question',
                'message' => 'I have a general question about the program.',
            ]);
            $request->setUserResolver(fn () => $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $response = $this->controller->store($request);

            $data = $response->getData(true);
            expect($data['ticket']['category'])->toBe('general');
            expect($data['ticket']['priority'])->toBe('normal');
        });

        test('creates initial message with ticket', function (): void {
            $request = Request::create('/affiliate/portal/support', 'POST', [
                'subject' => 'Technical issue',
                'message' => 'The tracking link is not working.',
            ]);
            $request->setUserResolver(fn () => $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $response = $this->controller->store($request);

            $data = $response->getData(true);
            expect($data['ticket']['messages'])->toHaveCount(1);
            expect($data['ticket']['messages'][0]['message'])->toBe('The tracking link is not working.');
            expect($data['ticket']['messages'][0]['is_staff_reply'])->toBeFalse();
        });

        test('validates required fields', function (): void {
            $request = Request::create('/affiliate/portal/support', 'POST', []);
            $request->setUserResolver(fn () => $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $this->controller->store($request);
        })->throws(Illuminate\Validation\ValidationException::class);

        test('validates category values', function (): void {
            $request = Request::create('/affiliate/portal/support', 'POST', [
                'subject' => 'Test',
                'message' => 'Test message',
                'category' => 'invalid_category',
            ]);
            $request->setUserResolver(fn () => $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $this->controller->store($request);
        })->throws(Illuminate\Validation\ValidationException::class);
    });

    describe('show', function (): void {
        test('returns ticket details', function (): void {
            $ticket = AffiliateSupportTicket::create([
                'affiliate_id' => $this->affiliate->id,
                'subject' => 'Detail Test Ticket',
                'category' => 'technical',
                'priority' => 'normal',
                'status' => 'open',
            ]);

            $request = Request::create("/affiliate/portal/support/{$ticket->id}", 'GET');
            $request->setUserResolver(fn () => $this->affiliate);

            $response = $this->controller->show($request, $ticket->id);

            expect($response->getStatusCode())->toBe(200);

            $data = $response->getData(true);
            expect($data['ticket']['id'])->toBe($ticket->id);
            expect($data['ticket']['subject'])->toBe('Detail Test Ticket');
        });

        test('includes messages with ticket', function (): void {
            $ticket = AffiliateSupportTicket::create([
                'affiliate_id' => $this->affiliate->id,
                'subject' => 'Messages Test',
                'category' => 'general',
                'priority' => 'normal',
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
                'affiliate_id' => null,
                'staff_id' => 'staff-123',
                'message' => 'Staff reply',
                'is_staff_reply' => true,
            ]);

            $request = Request::create("/affiliate/portal/support/{$ticket->id}", 'GET');
            $request->setUserResolver(fn () => $this->affiliate);

            $response = $this->controller->show($request, $ticket->id);

            $data = $response->getData(true);
            expect($data['ticket']['messages'])->toHaveCount(2);
        });

        test('throws 404 for non-existent ticket', function (): void {
            $request = Request::create('/affiliate/portal/support/non-existent-id', 'GET');
            $request->setUserResolver(fn () => $this->affiliate);

            $this->controller->show($request, 'non-existent-id');
        })->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

        test('throws 404 for another affiliate ticket', function (): void {
            $otherAffiliate = Affiliate::create([
                'code' => 'OTHER2-' . uniqid(),
                'name' => 'Other Affiliate 2',
                'contact_email' => 'other2@example.com',
                'status' => AffiliateStatus::Active,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $ticket = AffiliateSupportTicket::create([
                'affiliate_id' => $otherAffiliate->id,
                'subject' => 'Other Ticket',
                'category' => 'general',
                'priority' => 'normal',
                'status' => 'open',
            ]);

            $request = Request::create("/affiliate/portal/support/{$ticket->id}", 'GET');
            $request->setUserResolver(fn () => $this->affiliate);

            $this->controller->show($request, $ticket->id);
        })->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });

    describe('reply', function (): void {
        test('adds reply to ticket', function (): void {
            $ticket = AffiliateSupportTicket::create([
                'affiliate_id' => $this->affiliate->id,
                'subject' => 'Reply Test',
                'category' => 'general',
                'priority' => 'normal',
                'status' => 'open',
            ]);

            $request = Request::create("/affiliate/portal/support/{$ticket->id}/reply", 'POST', [
                'message' => 'This is my reply.',
            ]);
            $request->setUserResolver(fn () => $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $response = $this->controller->reply($request, $ticket->id);

            expect($response->getStatusCode())->toBe(200);

            $data = $response->getData(true);
            expect($data['message'])->toBe('Reply added successfully.');
            expect($data['ticket']['messages'])->toHaveCount(1);
        });

        test('updates ticket status to awaiting_response', function (): void {
            $ticket = AffiliateSupportTicket::create([
                'affiliate_id' => $this->affiliate->id,
                'subject' => 'Status Test',
                'category' => 'general',
                'priority' => 'normal',
                'status' => 'open',
            ]);

            $request = Request::create("/affiliate/portal/support/{$ticket->id}/reply", 'POST', [
                'message' => 'Waiting for response.',
            ]);
            $request->setUserResolver(fn () => $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $response = $this->controller->reply($request, $ticket->id);

            $data = $response->getData(true);
            expect($data['ticket']['status'])->toBe('awaiting_response');
        });

        test('cannot reply to closed ticket', function (): void {
            $ticket = AffiliateSupportTicket::create([
                'affiliate_id' => $this->affiliate->id,
                'subject' => 'Closed Ticket',
                'category' => 'general',
                'priority' => 'normal',
                'status' => 'closed',
            ]);

            $request = Request::create("/affiliate/portal/support/{$ticket->id}/reply", 'POST', [
                'message' => 'This should fail.',
            ]);
            $request->setUserResolver(fn () => $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $response = $this->controller->reply($request, $ticket->id);

            expect($response->getStatusCode())->toBe(422);

            $data = $response->getData(true);
            expect($data['error'])->toBe('Cannot reply to a closed ticket.');
        });

        test('validates message is required', function (): void {
            $ticket = AffiliateSupportTicket::create([
                'affiliate_id' => $this->affiliate->id,
                'subject' => 'Validation Test',
                'category' => 'general',
                'priority' => 'normal',
                'status' => 'open',
            ]);

            $request = Request::create("/affiliate/portal/support/{$ticket->id}/reply", 'POST', []);
            $request->setUserResolver(fn () => $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $this->controller->reply($request, $ticket->id);
        })->throws(Illuminate\Validation\ValidationException::class);

        test('throws 404 for non-existent ticket', function (): void {
            $request = Request::create('/affiliate/portal/support/non-existent/reply', 'POST', [
                'message' => 'Test',
            ]);
            $request->setUserResolver(fn () => $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $this->controller->reply($request, 'non-existent');
        })->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });

    describe('close', function (): void {
        test('closes ticket', function (): void {
            $ticket = AffiliateSupportTicket::create([
                'affiliate_id' => $this->affiliate->id,
                'subject' => 'Close Test',
                'category' => 'general',
                'priority' => 'normal',
                'status' => 'open',
            ]);

            $request = Request::create("/affiliate/portal/support/{$ticket->id}/close", 'POST');
            $request->setUserResolver(fn () => $this->affiliate);

            $response = $this->controller->close($request, $ticket->id);

            expect($response->getStatusCode())->toBe(200);

            $data = $response->getData(true);
            expect($data['ticket']['status'])->toBe('closed');
            expect($data['message'])->toBe('Ticket closed successfully.');
        });

        test('can close already closed ticket (idempotent)', function (): void {
            $ticket = AffiliateSupportTicket::create([
                'affiliate_id' => $this->affiliate->id,
                'subject' => 'Already Closed',
                'category' => 'general',
                'priority' => 'normal',
                'status' => 'closed',
            ]);

            $request = Request::create("/affiliate/portal/support/{$ticket->id}/close", 'POST');
            $request->setUserResolver(fn () => $this->affiliate);

            $response = $this->controller->close($request, $ticket->id);

            expect($response->getStatusCode())->toBe(200);

            $data = $response->getData(true);
            expect($data['ticket']['status'])->toBe('closed');
        });

        test('throws 404 for non-existent ticket', function (): void {
            $request = Request::create('/affiliate/portal/support/non-existent/close', 'POST');
            $request->setUserResolver(fn () => $this->affiliate);

            $this->controller->close($request, 'non-existent');
        })->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

        test('throws 404 for another affiliate ticket', function (): void {
            $otherAffiliate = Affiliate::create([
                'code' => 'OTHER3-' . uniqid(),
                'name' => 'Other Affiliate 3',
                'contact_email' => 'other3@example.com',
                'status' => AffiliateStatus::Active,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $ticket = AffiliateSupportTicket::create([
                'affiliate_id' => $otherAffiliate->id,
                'subject' => 'Other Affiliate Ticket',
                'category' => 'general',
                'priority' => 'normal',
                'status' => 'open',
            ]);

            $request = Request::create("/affiliate/portal/support/{$ticket->id}/close", 'POST');
            $request->setUserResolver(fn () => $this->affiliate);

            $this->controller->close($request, $ticket->id);
        })->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});
