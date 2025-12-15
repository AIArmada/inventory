<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerNote;

describe('CustomerNote Model', function (): void {
    beforeEach(function (): void {
        $this->customer = Customer::create([
            'first_name' => 'Note',
            'last_name' => 'Test',
            'email' => 'note-' . uniqid() . '@example.com',
            'status' => CustomerStatus::Active,
        ]);
    });

    describe('Creation', function (): void {
        it('can create a note', function (): void {
            $note = CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Test note content',
            ]);

            expect($note)->toBeInstanceOf(CustomerNote::class)
                ->and($note->id)->not->toBeEmpty()
                ->and($note->content)->toBe('Test note content');
        });

        it('defaults to internal', function (): void {
            $note = CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Internal default',
            ]);

            expect($note->is_internal)->toBeTrue();
        });

        it('defaults to not pinned', function (): void {
            $note = CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Not pinned default',
            ]);

            expect($note->is_pinned)->toBeFalse();
        });
    });

    describe('Relationships', function (): void {
        it('belongs to a customer', function (): void {
            $note = CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Relationship test',
            ]);

            expect($note->customer)->toBeInstanceOf(Customer::class)
                ->and($note->customer->id)->toBe($this->customer->id);
        });
    });

    describe('Helpers', function (): void {
        it('checks if internal', function (): void {
            $internal = CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Internal note',
                'is_internal' => true,
            ]);

            $public = CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Public note',
                'is_internal' => false,
            ]);

            expect($internal->isInternal())->toBeTrue()
                ->and($public->isInternal())->toBeFalse();
        });

        it('checks if visible to customer', function (): void {
            $internal = CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Internal visibility',
                'is_internal' => true,
            ]);

            $public = CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Public visibility',
                'is_internal' => false,
            ]);

            expect($internal->isVisibleToCustomer())->toBeFalse()
                ->and($public->isVisibleToCustomer())->toBeTrue();
        });

        it('can pin a note', function (): void {
            $note = CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Pin me',
            ]);

            $note->pin();

            expect($note->fresh()->is_pinned)->toBeTrue();
        });

        it('can unpin a note', function (): void {
            $note = CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Unpin me',
                'is_pinned' => true,
            ]);

            $note->unpin();

            expect($note->fresh()->is_pinned)->toBeFalse();
        });
    });

    describe('Scopes', function (): void {
        it('can filter internal notes', function (): void {
            CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Internal scope',
                'is_internal' => true,
            ]);

            CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Public scope',
                'is_internal' => false,
            ]);

            $internal = CustomerNote::internal()->get();

            expect($internal->every(fn ($n) => $n->is_internal))->toBeTrue();
        });

        it('can filter visible to customer notes', function (): void {
            CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Visible scope',
                'is_internal' => false,
            ]);

            $visible = CustomerNote::visibleToCustomer()->get();

            expect($visible->every(fn ($n) => ! $n->is_internal))->toBeTrue();
        });

        it('can filter pinned notes', function (): void {
            CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Pinned scope',
                'is_pinned' => true,
            ]);

            CustomerNote::create([
                'customer_id' => $this->customer->id,
                'content' => 'Not pinned scope',
                'is_pinned' => false,
            ]);

            $pinned = CustomerNote::pinned()->get();

            expect($pinned->every(fn ($n) => $n->is_pinned))->toBeTrue();
        });
    });
});
