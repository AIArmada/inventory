<?php

declare(strict_types=1);

use AIArmada\Products\Enums\AttributeType;

describe('AttributeType Enum', function (): void {
    describe('Values', function (): void {
        it('has all expected values', function (): void {
            expect(AttributeType::Text->value)->toBe('text')
                ->and(AttributeType::Textarea->value)->toBe('textarea')
                ->and(AttributeType::Number->value)->toBe('number')
                ->and(AttributeType::Boolean->value)->toBe('boolean')
                ->and(AttributeType::Select->value)->toBe('select')
                ->and(AttributeType::Multiselect->value)->toBe('multiselect')
                ->and(AttributeType::Date->value)->toBe('date')
                ->and(AttributeType::Color->value)->toBe('color')
                ->and(AttributeType::Media->value)->toBe('media');
        });

        it('can be created from string', function (): void {
            expect(AttributeType::from('text'))->toBe(AttributeType::Text)
                ->and(AttributeType::from('boolean'))->toBe(AttributeType::Boolean);
        });
    });

    describe('label()', function (): void {
        it('returns translation key for all types', function (): void {
            expect(AttributeType::Text->label())->not->toBeEmpty()
                ->and(AttributeType::Textarea->label())->not->toBeEmpty()
                ->and(AttributeType::Number->label())->not->toBeEmpty()
                ->and(AttributeType::Boolean->label())->not->toBeEmpty()
                ->and(AttributeType::Select->label())->not->toBeEmpty()
                ->and(AttributeType::Multiselect->label())->not->toBeEmpty()
                ->and(AttributeType::Date->label())->not->toBeEmpty()
                ->and(AttributeType::Color->label())->not->toBeEmpty()
                ->and(AttributeType::Media->label())->not->toBeEmpty();
        });
    });

    describe('icon()', function (): void {
        it('returns correct icon for text', function (): void {
            expect(AttributeType::Text->icon())->toBe('heroicon-o-pencil');
        });

        it('returns correct icon for textarea', function (): void {
            expect(AttributeType::Textarea->icon())->toBe('heroicon-o-document-text');
        });

        it('returns correct icon for number', function (): void {
            expect(AttributeType::Number->icon())->toBe('heroicon-o-hashtag');
        });

        it('returns correct icon for boolean', function (): void {
            expect(AttributeType::Boolean->icon())->toBe('heroicon-o-check-circle');
        });

        it('returns correct icon for select', function (): void {
            expect(AttributeType::Select->icon())->toBe('heroicon-o-chevron-down');
        });

        it('returns correct icon for multiselect', function (): void {
            expect(AttributeType::Multiselect->icon())->toBe('heroicon-o-list-bullet');
        });

        it('returns correct icon for date', function (): void {
            expect(AttributeType::Date->icon())->toBe('heroicon-o-calendar');
        });

        it('returns correct icon for color', function (): void {
            expect(AttributeType::Color->icon())->toBe('heroicon-o-swatch');
        });

        it('returns correct icon for media', function (): void {
            expect(AttributeType::Media->icon())->toBe('heroicon-o-photo');
        });
    });

    describe('color()', function (): void {
        it('returns correct colors for all types', function (): void {
            expect(AttributeType::Text->color())->toBe('gray')
                ->and(AttributeType::Textarea->color())->toBe('gray')
                ->and(AttributeType::Number->color())->toBe('info')
                ->and(AttributeType::Boolean->color())->toBe('success')
                ->and(AttributeType::Select->color())->toBe('warning')
                ->and(AttributeType::Multiselect->color())->toBe('warning')
                ->and(AttributeType::Date->color())->toBe('primary')
                ->and(AttributeType::Color->color())->toBe('danger')
                ->and(AttributeType::Media->color())->toBe('info');
        });
    });

    describe('hasOptions()', function (): void {
        it('returns true for select', function (): void {
            expect(AttributeType::Select->hasOptions())->toBeTrue();
        });

        it('returns true for multiselect', function (): void {
            expect(AttributeType::Multiselect->hasOptions())->toBeTrue();
        });

        it('returns false for text', function (): void {
            expect(AttributeType::Text->hasOptions())->toBeFalse();
        });

        it('returns false for number', function (): void {
            expect(AttributeType::Number->hasOptions())->toBeFalse();
        });

        it('returns false for boolean', function (): void {
            expect(AttributeType::Boolean->hasOptions())->toBeFalse();
        });

        it('returns false for date', function (): void {
            expect(AttributeType::Date->hasOptions())->toBeFalse();
        });
    });

    describe('isMultiple()', function (): void {
        it('returns true for multiselect', function (): void {
            expect(AttributeType::Multiselect->isMultiple())->toBeTrue();
        });

        it('returns false for select', function (): void {
            expect(AttributeType::Select->isMultiple())->toBeFalse();
        });

        it('returns false for text', function (): void {
            expect(AttributeType::Text->isMultiple())->toBeFalse();
        });
    });

    describe('defaultValidation()', function (): void {
        it('returns correct validation for text', function (): void {
            expect(AttributeType::Text->defaultValidation())->toBe(['string', 'max:255']);
        });

        it('returns correct validation for textarea', function (): void {
            expect(AttributeType::Textarea->defaultValidation())->toBe(['string', 'max:65535']);
        });

        it('returns correct validation for number', function (): void {
            expect(AttributeType::Number->defaultValidation())->toBe(['numeric']);
        });

        it('returns correct validation for boolean', function (): void {
            expect(AttributeType::Boolean->defaultValidation())->toBe(['boolean']);
        });

        it('returns correct validation for select', function (): void {
            expect(AttributeType::Select->defaultValidation())->toBe(['string']);
        });

        it('returns correct validation for multiselect', function (): void {
            expect(AttributeType::Multiselect->defaultValidation())->toBe(['array']);
        });

        it('returns correct validation for date', function (): void {
            expect(AttributeType::Date->defaultValidation())->toBe(['date']);
        });

        it('returns correct validation for color', function (): void {
            expect(AttributeType::Color->defaultValidation())->toBe(['string', 'regex:/^#[0-9A-Fa-f]{6}$/']);
        });

        it('returns correct validation for media', function (): void {
            expect(AttributeType::Media->defaultValidation())->toBe(['string']);
        });
    });

    describe('castValue()', function (): void {
        it('returns null for null value', function (): void {
            expect(AttributeType::Text->castValue(null))->toBeNull();
        });

        it('casts text to string', function (): void {
            expect(AttributeType::Text->castValue(123))->toBe('123');
        });

        it('casts textarea to string', function (): void {
            expect(AttributeType::Textarea->castValue(123))->toBe('123');
        });

        it('casts number to float', function (): void {
            expect(AttributeType::Number->castValue('42.5'))->toBe(42.5);
        });

        it('returns null for non-numeric number', function (): void {
            expect(AttributeType::Number->castValue('not a number'))->toBeNull();
        });

        it('casts boolean to bool', function (): void {
            expect(AttributeType::Boolean->castValue('1'))->toBeTrue()
                ->and(AttributeType::Boolean->castValue('0'))->toBeFalse();
        });

        it('casts select to string', function (): void {
            expect(AttributeType::Select->castValue('option1'))->toBe('option1');
        });

        it('casts color to string', function (): void {
            expect(AttributeType::Color->castValue('#FF0000'))->toBe('#FF0000');
        });

        it('casts media to string', function (): void {
            expect(AttributeType::Media->castValue('/path/to/file.jpg'))->toBe('/path/to/file.jpg');
        });

        it('casts multiselect from array', function (): void {
            $result = AttributeType::Multiselect->castValue(['a', 'b', 'c']);
            expect($result)->toBe(['a', 'b', 'c']);
        });

        it('casts multiselect from json string', function (): void {
            $result = AttributeType::Multiselect->castValue('["a","b","c"]');
            expect($result)->toBe(['a', 'b', 'c']);
        });

        it('casts date from string', function (): void {
            $result = AttributeType::Date->castValue('2024-01-15');
            expect($result)->toBeInstanceOf(DateTimeImmutable::class);
        });

        it('passes through date objects', function (): void {
            $date = new DateTimeImmutable('2024-01-15');
            $result = AttributeType::Date->castValue($date);
            expect($result)->toBe($date);
        });
    });

    describe('serializeValue()', function (): void {
        it('returns null for null value', function (): void {
            expect(AttributeType::Text->serializeValue(null))->toBeNull();
        });

        it('serializes text to string', function (): void {
            expect(AttributeType::Text->serializeValue('hello'))->toBe('hello');
        });

        it('serializes textarea to string', function (): void {
            expect(AttributeType::Textarea->serializeValue('hello'))->toBe('hello');
        });

        it('serializes number to string', function (): void {
            expect(AttributeType::Number->serializeValue(42.5))->toBe('42.5');
        });

        it('serializes true boolean to 1', function (): void {
            expect(AttributeType::Boolean->serializeValue(true))->toBe('1');
        });

        it('serializes false boolean to 0', function (): void {
            expect(AttributeType::Boolean->serializeValue(false))->toBe('0');
        });

        it('serializes select to string', function (): void {
            expect(AttributeType::Select->serializeValue('option1'))->toBe('option1');
        });

        it('serializes color to string', function (): void {
            expect(AttributeType::Color->serializeValue('#FF0000'))->toBe('#FF0000');
        });

        it('serializes media to string', function (): void {
            expect(AttributeType::Media->serializeValue('/path/to/file.jpg'))->toBe('/path/to/file.jpg');
        });

        it('serializes multiselect to json', function (): void {
            $result = AttributeType::Multiselect->serializeValue(['a', 'b', 'c']);
            expect($result)->toBe('["a","b","c"]');
        });

        it('serializes date object to Y-m-d', function (): void {
            $date = new DateTimeImmutable('2024-01-15');
            expect(AttributeType::Date->serializeValue($date))->toBe('2024-01-15');
        });

        it('serializes date string as is', function (): void {
            expect(AttributeType::Date->serializeValue('2024-01-15'))->toBe('2024-01-15');
        });
    });
});
