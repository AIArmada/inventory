<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\ReorderSuggestionStatus;

test('ReorderSuggestionStatus enum has correct cases', function (): void {
    expect(ReorderSuggestionStatus::cases())->toHaveCount(6);
    expect(ReorderSuggestionStatus::Pending->value)->toBe('pending');
    expect(ReorderSuggestionStatus::Approved->value)->toBe('approved');
    expect(ReorderSuggestionStatus::Ordered->value)->toBe('ordered');
    expect(ReorderSuggestionStatus::Received->value)->toBe('received');
    expect(ReorderSuggestionStatus::Rejected->value)->toBe('rejected');
    expect(ReorderSuggestionStatus::Expired->value)->toBe('expired');
});

test('ReorderSuggestionStatus label returns correct labels', function (): void {
    expect(ReorderSuggestionStatus::Pending->label())->toBe('Pending Review');
    expect(ReorderSuggestionStatus::Approved->label())->toBe('Approved');
    expect(ReorderSuggestionStatus::Ordered->label())->toBe('Ordered');
    expect(ReorderSuggestionStatus::Received->label())->toBe('Received');
    expect(ReorderSuggestionStatus::Rejected->label())->toBe('Rejected');
    expect(ReorderSuggestionStatus::Expired->label())->toBe('Expired');
});

test('ReorderSuggestionStatus color returns correct colors', function (): void {
    expect(ReorderSuggestionStatus::Pending->color())->toBe('warning');
    expect(ReorderSuggestionStatus::Approved->color())->toBe('info');
    expect(ReorderSuggestionStatus::Ordered->color())->toBe('primary');
    expect(ReorderSuggestionStatus::Received->color())->toBe('success');
    expect(ReorderSuggestionStatus::Rejected->color())->toBe('danger');
    expect(ReorderSuggestionStatus::Expired->color())->toBe('gray');
});

test('ReorderSuggestionStatus isActionable works correctly', function (): void {
    expect(ReorderSuggestionStatus::Pending->isActionable())->toBeTrue();
    expect(ReorderSuggestionStatus::Approved->isActionable())->toBeTrue();
    expect(ReorderSuggestionStatus::Ordered->isActionable())->toBeFalse();
    expect(ReorderSuggestionStatus::Received->isActionable())->toBeFalse();
    expect(ReorderSuggestionStatus::Rejected->isActionable())->toBeFalse();
    expect(ReorderSuggestionStatus::Expired->isActionable())->toBeFalse();
});

test('ReorderSuggestionStatus isComplete works correctly', function (): void {
    expect(ReorderSuggestionStatus::Pending->isComplete())->toBeFalse();
    expect(ReorderSuggestionStatus::Approved->isComplete())->toBeFalse();
    expect(ReorderSuggestionStatus::Ordered->isComplete())->toBeFalse();
    expect(ReorderSuggestionStatus::Received->isComplete())->toBeTrue();
    expect(ReorderSuggestionStatus::Rejected->isComplete())->toBeTrue();
    expect(ReorderSuggestionStatus::Expired->isComplete())->toBeTrue();
});
