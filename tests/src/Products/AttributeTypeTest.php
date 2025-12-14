<?php

declare(strict_types=1);

use AIArmada\Products\Enums\AttributeType;

describe('AttributeType Enum', function (): void {
    describe('Cases', function (): void {
        it('has all expected cases', function (): void {
            expect(AttributeType::cases())->toHaveCount(9);
            expect(AttributeType::Text)->toBeInstanceOf(AttributeType::class);
            expect(AttributeType::Textarea)->toBeInstanceOf(AttributeType::class);
            expect(AttributeType::Number)->toBeInstanceOf(AttributeType::class);
            expect(AttributeType::Boolean)->toBeInstanceOf(AttributeType::class);
            expect(AttributeType::Select)->toBeInstanceOf(AttributeType::class);
            expect(AttributeType::Multiselect)->toBeInstanceOf(AttributeType::class);
            expect(AttributeType::Date)->toBeInstanceOf(AttributeType::class);
            expect(AttributeType::Color)->toBeInstanceOf(AttributeType::class);
            expect(AttributeType::Media)->toBeInstanceOf(AttributeType::class);
        });
    });

    describe('Label Method', function (): void {
        it('returns label for text type', function (): void {
            expect(AttributeType::Text->label())->toBe(__('products::enums.attribute_type.text'));
        });

        it('returns label for textarea type', function (): void {
            expect(AttributeType::Textarea->label())->toBe(__('products::enums.attribute_type.textarea'));
        });

        it('returns label for number type', function (): void {
            expect(AttributeType::Number->label())->toBe(__('products::enums.attribute_type.number'));
        });

        it('returns label for boolean type', function (): void {
            expect(AttributeType::Boolean->label())->toBe(__('products::enums.attribute_type.boolean'));
        });

        it('returns label for select type', function (): void {
            expect(AttributeType::Select->label())->toBe(__('products::enums.attribute_type.select'));
        });

        it('returns label for multiselect type', function (): void {
            expect(AttributeType::Multiselect->label())->toBe(__('products::enums.attribute_type.multiselect'));
        });

        it('returns label for date type', function (): void {
            expect(AttributeType::Date->label())->toBe(__('products::enums.attribute_type.date'));
        });

        it('returns label for color type', function (): void {
            expect(AttributeType::Color->label())->toBe(__('products::enums.attribute_type.color'));
        });

        it('returns label for media type', function (): void {
            expect(AttributeType::Media->label())->toBe(__('products::enums.attribute_type.media'));
        });
    });

    describe('Icon Method', function (): void {
        it('returns correct icons for each type', function (): void {
            expect(AttributeType::Text->icon())->toBe('heroicon-o-pencil');
            expect(AttributeType::Textarea->icon())->toBe('heroicon-o-document-text');
            expect(AttributeType::Number->icon())->toBe('heroicon-o-hashtag');
            expect(AttributeType::Boolean->icon())->toBe('heroicon-o-check-circle');
            expect(AttributeType::Select->icon())->toBe('heroicon-o-chevron-down');
            expect(AttributeType::Multiselect->icon())->toBe('heroicon-o-list-bullet');
            expect(AttributeType::Date->icon())->toBe('heroicon-o-calendar');
            expect(AttributeType::Color->icon())->toBe('heroicon-o-swatch');
            expect(AttributeType::Media->icon())->toBe('heroicon-o-photo');
        });
    });

    describe('Color Method', function (): void {
        it('returns correct colors for each type', function (): void {
            expect(AttributeType::Text->color())->toBe('gray');
            expect(AttributeType::Textarea->color())->toBe('gray');
            expect(AttributeType::Number->color())->toBe('info');
            expect(AttributeType::Boolean->color())->toBe('success');
            expect(AttributeType::Select->color())->toBe('warning');
            expect(AttributeType::Multiselect->color())->toBe('warning');
            expect(AttributeType::Date->color())->toBe('primary');
            expect(AttributeType::Color->color())->toBe('danger');
            expect(AttributeType::Media->color())->toBe('info');
        });
    });

    describe('Has Options Method', function (): void {
        it('returns true for select types', function (): void {
            expect(AttributeType::Select->hasOptions())->toBeTrue();
            expect(AttributeType::Multiselect->hasOptions())->toBeTrue();
        });

        it('returns false for non-select types', function (): void {
            expect(AttributeType::Text->hasOptions())->toBeFalse();
            expect(AttributeType::Textarea->hasOptions())->toBeFalse();
            expect(AttributeType::Number->hasOptions())->toBeFalse();
            expect(AttributeType::Boolean->hasOptions())->toBeFalse();
            expect(AttributeType::Date->hasOptions())->toBeFalse();
            expect(AttributeType::Color->hasOptions())->toBeFalse();
            expect(AttributeType::Media->hasOptions())->toBeFalse();
        });
    });

    describe('Is Multiple Method', function (): void {
        it('returns true only for multiselect', function (): void {
            expect(AttributeType::Multiselect->isMultiple())->toBeTrue();
        });

        it('returns false for all other types', function (): void {
            expect(AttributeType::Text->isMultiple())->toBeFalse();
            expect(AttributeType::Textarea->isMultiple())->toBeFalse();
            expect(AttributeType::Number->isMultiple())->toBeFalse();
            expect(AttributeType::Boolean->isMultiple())->toBeFalse();
            expect(AttributeType::Select->isMultiple())->toBeFalse();
            expect(AttributeType::Date->isMultiple())->toBeFalse();
            expect(AttributeType::Color->isMultiple())->toBeFalse();
            expect(AttributeType::Media->isMultiple())->toBeFalse();
        });
    });

    describe('Default Validation Method', function (): void {
        it('returns correct validation rules for each type', function (): void {
            expect(AttributeType::Text->defaultValidation())->toBe(['string', 'max:255']);
            expect(AttributeType::Textarea->defaultValidation())->toBe(['string', 'max:65535']);
            expect(AttributeType::Number->defaultValidation())->toBe(['numeric']);
            expect(AttributeType::Boolean->defaultValidation())->toBe(['boolean']);
            expect(AttributeType::Select->defaultValidation())->toBe(['string']);
            expect(AttributeType::Multiselect->defaultValidation())->toBe(['array']);
            expect(AttributeType::Date->defaultValidation())->toBe(['date']);
            expect(AttributeType::Color->defaultValidation())->toBe(['string', 'regex:/^#[0-9A-Fa-f]{6}$/']);
            expect(AttributeType::Media->defaultValidation())->toBe(['string']);
        });
    });

    describe('Cast Value Method', function (): void {
        it('casts null values correctly', function (): void {
            expect(AttributeType::Text->castValue(null))->toBeNull();
        });

        it('casts text values correctly', function (): void {
            expect(AttributeType::Text->castValue('hello'))->toBe('hello');
            expect(AttributeType::Text->castValue(123))->toBe('123');
        });

        it('casts number values correctly', function (): void {
            expect(AttributeType::Number->castValue('123.45'))->toBe(123.45);
            expect(AttributeType::Number->castValue(123))->toBe(123.0);
            expect(AttributeType::Number->castValue('invalid'))->toBeNull();
        });

        it('casts boolean values correctly', function (): void {
            expect(AttributeType::Boolean->castValue('1'))->toBeTrue();
            expect(AttributeType::Boolean->castValue('0'))->toBeFalse();
            expect(AttributeType::Boolean->castValue(true))->toBeTrue();
            expect(AttributeType::Boolean->castValue(false))->toBeFalse();
        });

        it('casts multiselect values correctly', function (): void {
            $arrayValue = ['option1', 'option2'];
            expect(AttributeType::Multiselect->castValue($arrayValue))->toBe($arrayValue);
            expect(AttributeType::Multiselect->castValue('["option1","option2"]'))->toBe($arrayValue);
        });

        it('casts date values correctly', function (): void {
            $dateString = '2023-12-25';
            $result = AttributeType::Date->castValue($dateString);
            expect($result)->toBeInstanceOf(DateTimeImmutable::class);
            expect($result->format('Y-m-d'))->toBe($dateString);
        });
    });

    describe('Serialize Value Method', function (): void {
        it('serializes null values correctly', function (): void {
            expect(AttributeType::Text->serializeValue(null))->toBeNull();
        });

        it('serializes text values correctly', function (): void {
            expect(AttributeType::Text->serializeValue('hello'))->toBe('hello');
        });

        it('serializes number values correctly', function (): void {
            expect(AttributeType::Number->serializeValue(123.45))->toBe('123.45');
        });

        it('serializes boolean values correctly', function (): void {
            expect(AttributeType::Boolean->serializeValue(true))->toBe('1');
            expect(AttributeType::Boolean->serializeValue(false))->toBe('0');
        });

        it('serializes multiselect values correctly', function (): void {
            $arrayValue = ['option1', 'option2'];
            expect(AttributeType::Multiselect->serializeValue($arrayValue))->toBe('["option1","option2"]');
        });

        it('serializes date values correctly', function (): void {
            $date = new DateTimeImmutable('2023-12-25');
            expect(AttributeType::Date->serializeValue($date))->toBe('2023-12-25');
        });
    });
});
