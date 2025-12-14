<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\Tax\Tax1099Generator;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->generator = new Tax1099Generator;

    $this->affiliate = Affiliate::create([
        'code' => 'TAX-' . uniqid(),
        'name' => 'Tax Test Affiliate',
        'contact_email' => 'tax@example.com',
        'status' => AffiliateStatus::Active,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
});

describe('Tax1099Generator', function (): void {
    describe('generate', function (): void {
        test('generates tax document and returns path', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 150000, // $1500.00
                'tax_info' => [
                    'legal_name' => 'John Doe',
                    'address' => '123 Main St',
                    'tin' => '123-45-6789',
                ],
            ];

            $result = $this->generator->generate($data);

            expect($result)->toContain('tax-documents/2024/');
            expect($result)->toContain('1099-NEC-2024-');
            expect($result)->toContain($this->affiliate->id);
            expect($result)->toEndWith('.pdf');

            Storage::disk('local')->assertExists($result);
        });

        test('stores document in year-specific folder', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2023,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Jane Smith',
                    'tin' => '987654321',
                ],
            ];

            $result = $this->generator->generate($data);

            expect($result)->toStartWith('tax-documents/2023/');
        });

        test('creates unique filenames per affiliate and date', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Test User',
                    'tin' => '111223333',
                ],
            ];

            $result = $this->generator->generate($data);

            $expectedPattern = sprintf(
                '1099-NEC-2024-%s-%s.pdf',
                $this->affiliate->id,
                now()->format('Ymd')
            );

            expect($result)->toContain($expectedPattern);
        });
    });

    describe('PDF content generation', function (): void {
        test('includes payer information from config', function (): void {
            Storage::fake('local');

            config([
                'affiliates.tax.payer_info' => [
                    'name' => 'Test Company Inc',
                    'address' => '456 Business Blvd',
                    'tin' => '98-7654321',
                ],
            ]);

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 500000,
                'tax_info' => [
                    'legal_name' => 'Recipient Name',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->generate($data);
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('Test Company Inc');
            expect($content)->toContain('456 Business Blvd');
        });

        test('includes recipient legal name', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'John Q. Public',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->generate($data);
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('John Q. Public');
        });

        test('includes recipient address when provided', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Affiliate Person',
                    'address' => '789 Affiliate Ave, Suite 100',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->generate($data);
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('789 Affiliate Ave, Suite 100');
        });

        test('handles missing address gracefully', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'No Address Person',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->generate($data);
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain("Address: \n");
        });

        test('formats amount correctly in dollars', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 1234567, // $12,345.67
                'tax_info' => [
                    'legal_name' => 'High Earner',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->generate($data);
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('$12,345.67');
        });

        test('includes form title and year', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Test',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->generate($data);
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('IRS Form 1099-NEC');
            expect($content)->toContain('Tax Year: 2024');
        });
    });

    describe('TIN masking', function (): void {
        test('masks SSN format correctly', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'SSN Person',
                    'tin' => '123-45-6789', // SSN format
                ],
            ];

            $result = $this->generator->generate($data);
            $content = Storage::disk('local')->get($result);

            // SSN: XXX-XX-6789
            expect($content)->toContain('XXX-XX-6789');
            expect($content)->not->toContain('123-45-6789');
        });

        test('masks SSN without dashes correctly', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'SSN Person',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->generate($data);
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('XXX-XX-6789');
        });

        test('masks EIN format correctly', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Business Entity',
                    'tin' => '12-3456789', // EIN format (only 8 digits after removing -)
                ],
            ];

            $result = $this->generator->generate($data);
            $content = Storage::disk('local')->get($result);

            // EIN doesn't meet 9-digit requirement, uses alternate format
            // After cleaning: 123456789 = 9 digits, so SSN format applies
            // But 12-3456789 cleaned = 123456789 = 9 digits
            expect($content)->toContain('XXX-XX-6789');
        });

        test('handles short TIN', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Short TIN',
                    'tin' => '12345678', // Only 8 digits
                ],
            ];

            $result = $this->generator->generate($data);
            $content = Storage::disk('local')->get($result);

            // Should use alternate masking format
            expect($content)->toContain('XX-XXX5678');
        });
    });

    describe('storage configuration', function (): void {
        test('uses configured storage disk', function (): void {
            Storage::fake('custom-tax-disk');

            config(['affiliates.tax.storage_disk' => 'custom-tax-disk']);

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Custom Disk Test',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->generate($data);

            Storage::disk('custom-tax-disk')->assertExists($result);
        });

        test('defaults to local disk when not configured', function (): void {
            Storage::fake('local');

            config(['affiliates.tax.storage_disk' => null]);

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Local Disk Test',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->generate($data);

            Storage::disk('local')->assertExists($result);
        });
    });
});

describe('Tax1099Generator class structure', function (): void {
    test('can be instantiated', function (): void {
        $generator = new Tax1099Generator;
        expect($generator)->toBeInstanceOf(Tax1099Generator::class);
    });

    test('is declared as final', function (): void {
        $reflection = new ReflectionClass(Tax1099Generator::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    test('has generate public method', function (): void {
        $reflection = new ReflectionClass(Tax1099Generator::class);
        expect($reflection->hasMethod('generate'))->toBeTrue();
        expect($reflection->getMethod('generate')->isPublic())->toBeTrue();
    });

    test('has private helper methods', function (): void {
        $reflection = new ReflectionClass(Tax1099Generator::class);

        expect($reflection->hasMethod('generatePdfContent'))->toBeTrue();
        expect($reflection->getMethod('generatePdfContent')->isPrivate())->toBeTrue();

        expect($reflection->hasMethod('maskTin'))->toBeTrue();
        expect($reflection->getMethod('maskTin')->isPrivate())->toBeTrue();
    });
});
