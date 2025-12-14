<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\GenerateAffiliateCode;
use AIArmada\Affiliates\Models\Affiliate;

describe('GenerateAffiliateCode', function (): void {
    describe('handle', function (): void {
        test('generates code with name prefix', function (): void {
            $action = new GenerateAffiliateCode();

            $code = $action->handle('John Smith');

            // Code should start with uppercase letters from the name
            expect($code)->toBeString();
            expect(mb_strlen($code))->toBeGreaterThanOrEqual(7); // at least base + 4 random
            // Str::random can include numbers, so check for alphanumeric uppercase
            expect($code)->toMatch('/^[A-Z0-9]+$/');
        });

        test('generates code with empty name uses AFF prefix', function (): void {
            $action = new GenerateAffiliateCode();

            $code = $action->handle('');

            expect($code)->toStartWith('AFF');
            expect(mb_strlen($code))->toBe(7); // AFF + 4 random
        });

        test('generates code with no argument uses AFF prefix', function (): void {
            $action = new GenerateAffiliateCode();

            $code = $action->handle();

            expect($code)->toStartWith('AFF');
        });

        test('generates code with only dashes uses AFF prefix', function (): void {
            $action = new GenerateAffiliateCode();

            // Dashes that slug() removes entirely
            $code = $action->handle('---');

            // Should fall back to AFF when slug produces empty string
            expect($code)->toStartWith('AFF');
        });

        test('generates code with whitespace only uses AFF prefix', function (): void {
            $action = new GenerateAffiliateCode();

            $code = $action->handle('   ');

            expect($code)->toStartWith('AFF');
        });

        test('truncates long names to 6 characters', function (): void {
            $action = new GenerateAffiliateCode();

            $code = $action->handle('Alexander The Great');

            // Should use first 6 chars: ALEXAN
            expect(mb_strlen($code))->toBe(10); // 6 + 4 random
        });

        test('generates unique code when collision occurs', function (): void {
            // First, create an affiliate with a known code pattern
            $existingAffiliate = Affiliate::create([
                'code' => 'TESTAB1234',
                'name' => 'Existing Test',
                'contact_email' => 'existing@test.com',
                'status' => 'active',
                'commission_type' => 'percentage',
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $action = new GenerateAffiliateCode();

            // Generate multiple codes - they should all be unique
            $codes = [];
            for ($i = 0; $i < 10; $i++) {
                $code = $action->handle('Test AB');
                $codes[] = $code;
            }

            // All codes should be unique
            expect(count($codes))->toBe(count(array_unique($codes)));

            // None should match the existing affiliate's code
            expect(in_array($existingAffiliate->code, $codes, true))->toBeFalse();
        });

        test('handles unicode characters in name', function (): void {
            $action = new GenerateAffiliateCode();

            $code = $action->handle('José García');

            expect($code)->toBeString();
            expect(mb_strlen($code))->toBeGreaterThanOrEqual(7);
            // Str::random can include numbers, so check for alphanumeric uppercase
            expect($code)->toMatch('/^[A-Z0-9]+$/');
        });

        test('handles numbers in name', function (): void {
            $action = new GenerateAffiliateCode();

            $code = $action->handle('Affiliate123');

            expect($code)->toBeString();
            expect(mb_strlen($code))->toBeGreaterThanOrEqual(7);
        });

        test('handles mixed case consistently', function (): void {
            $action = new GenerateAffiliateCode();

            $code = $action->handle('JoHn DoE');

            // All uppercase (Str::random can include numbers)
            expect($code)->toMatch('/^[A-Z0-9]+$/');
        });
    });

    describe('class structure', function (): void {
        test('can be instantiated', function (): void {
            $action = new GenerateAffiliateCode();

            expect($action)->toBeInstanceOf(GenerateAffiliateCode::class);
        });

        test('is declared as final', function (): void {
            $reflection = new ReflectionClass(GenerateAffiliateCode::class);

            expect($reflection->isFinal())->toBeTrue();
        });

        test('uses AsAction trait', function (): void {
            $traits = class_uses_recursive(GenerateAffiliateCode::class);

            expect($traits)->toContain(Lorisleiva\Actions\Concerns\AsAction::class);
        });
    });
});
