<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Http\Controllers\AffiliateApiController;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
use Illuminate\Http\Request;

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'API-TEST-' . uniqid(),
        'name' => 'API Test Affiliate',
        'contact_email' => 'api@example.com',
        'status' => AffiliateStatus::Active,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $this->controller = app(AffiliateApiController::class);
});

describe('AffiliateApiController', function (): void {
    test('can be instantiated', function (): void {
        $affiliateService = app(AffiliateService::class);
        $reportService = app(AffiliateReportService::class);
        $linkGenerator = app(AffiliateLinkGenerator::class);

        $controller = new AffiliateApiController(
            $affiliateService,
            $reportService,
            $linkGenerator
        );

        expect($controller)->toBeInstanceOf(AffiliateApiController::class);
    });

    describe('summary', function (): void {
        test('returns affiliate summary', function (): void {
            $response = $this->controller->summary($this->affiliate->code);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data)->toBeArray();
        });

        test('returns 404 for unknown affiliate code', function (): void {
            $response = $this->controller->summary('NONEXISTENT-CODE');

            expect($response->getStatusCode())->toBe(404);

            $data = json_decode($response->getContent(), true);
            expect($data['message'])->toBe('Affiliate not found');
        });
    });

    describe('links', function (): void {
        test('generates affiliate link', function (): void {
            $request = Request::create('/api/affiliates/links', 'GET', [
                'url' => 'https://example.com/products',
            ]);

            $response = $this->controller->links($this->affiliate->code, $request);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('link');
            expect($data['link'])->toContain($this->affiliate->code);
        });

        test('generates link with default URL', function (): void {
            $request = Request::create('/api/affiliates/links', 'GET');

            $response = $this->controller->links($this->affiliate->code, $request);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('link');
        });

        test('generates link with custom params', function (): void {
            $request = Request::create('/api/affiliates/links', 'GET', [
                'url' => 'https://example.com/products',
                'params' => ['campaign' => 'summer'],
            ]);

            $response = $this->controller->links($this->affiliate->code, $request);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data['link'])->toContain('campaign=summer');
        });

        test('returns 404 for unknown affiliate code', function (): void {
            $request = Request::create('/api/affiliates/links', 'GET');

            $response = $this->controller->links('NONEXISTENT-CODE', $request);

            expect($response->getStatusCode())->toBe(404);

            $data = json_decode($response->getContent(), true);
            expect($data['message'])->toBe('Affiliate not found');
        });

        test('generates link with TTL', function (): void {
            $request = Request::create('/api/affiliates/links', 'GET', [
                'url' => 'https://example.com/products',
                'ttl' => 86400,
            ]);

            $response = $this->controller->links($this->affiliate->code, $request);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('link');
        });
    });

    describe('creatives', function (): void {
        test('returns empty creatives for affiliate without metadata', function (): void {
            $response = $this->controller->creatives($this->affiliate->code);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data['creatives'])->toBeArray();
            expect($data['creatives'])->toBeEmpty();
        });

        test('returns creatives from metadata', function (): void {
            $this->affiliate->update([
                'metadata' => [
                    'creatives' => [
                        ['type' => 'banner', 'url' => 'https://example.com/banner.jpg'],
                        ['type' => 'text', 'content' => 'Best deals!'],
                    ],
                ],
            ]);

            $response = $this->controller->creatives($this->affiliate->code);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data['creatives'])->toHaveCount(2);
            expect($data['creatives'][0]['type'])->toBe('banner');
        });

        test('returns 404 for unknown affiliate code', function (): void {
            $response = $this->controller->creatives('NONEXISTENT-CODE');

            expect($response->getStatusCode())->toBe(404);

            $data = json_decode($response->getContent(), true);
            expect($data['message'])->toBe('Affiliate not found');
        });
    });
});

describe('AffiliateApiController class structure', function (): void {
    test('is declared as final', function (): void {
        $reflection = new ReflectionClass(AffiliateApiController::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    test('has required public methods', function (): void {
        $reflection = new ReflectionClass(AffiliateApiController::class);

        expect($reflection->hasMethod('summary'))->toBeTrue();
        expect($reflection->hasMethod('links'))->toBeTrue();
        expect($reflection->hasMethod('creatives'))->toBeTrue();
    });
});
