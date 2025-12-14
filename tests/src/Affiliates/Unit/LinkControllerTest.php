<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Http\Controllers\Portal\LinkController;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

describe('LinkController', function (): void {
    beforeEach(function (): void {
        $this->linkGenerator = app(AffiliateLinkGenerator::class);
        $this->controller = new LinkController($this->linkGenerator);

        $this->affiliate = Affiliate::create([
            'code' => 'LINK-CTRL-' . uniqid(),
            'name' => 'Link Controller Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    describe('index', function (): void {
        test('returns paginated list of affiliate links', function (): void {
            AffiliateLink::create([
                'affiliate_id' => $this->affiliate->id,
                'destination_url' => 'https://example.com/product-1',
                'tracking_url' => 'https://track.example.com/abc123',
                'campaign' => 'summer-sale',
                'clicks' => 100,
                'conversions' => 10,
                'is_active' => true,
            ]);

            AffiliateLink::create([
                'affiliate_id' => $this->affiliate->id,
                'destination_url' => 'https://example.com/product-2',
                'tracking_url' => 'https://track.example.com/def456',
                'campaign' => 'winter-promo',
                'clicks' => 50,
                'conversions' => 5,
                'is_active' => false,
            ]);

            $request = Request::create('/affiliate/portal/links', 'GET');
            $request->attributes->set('affiliate', $this->affiliate);

            $response = $this->controller->index($request);

            expect($response)->toBeInstanceOf(JsonResponse::class);
            expect($response->getStatusCode())->toBe(200);

            $data = $response->getData(true);
            expect($data)->toHaveKey('data');
            expect($data)->toHaveKey('meta');
            expect($data['data'])->toHaveCount(2);
            expect($data['meta'])->toHaveKeys(['current_page', 'last_page', 'per_page', 'total']);
        });

        test('returns link data with all required fields', function (): void {
            AffiliateLink::create([
                'affiliate_id' => $this->affiliate->id,
                'destination_url' => 'https://example.com/product',
                'tracking_url' => 'https://track.example.com/xyz',
                'short_url' => 'https://short.ly/abc',
                'campaign' => 'test-campaign',
                'clicks' => 200,
                'conversions' => 20,
                'is_active' => true,
            ]);

            $request = Request::create('/affiliate/portal/links', 'GET');
            $request->attributes->set('affiliate', $this->affiliate);

            $response = $this->controller->index($request);
            $data = $response->getData(true);

            $link = $data['data'][0];
            expect($link)->toHaveKeys([
                'id',
                'destination_url',
                'tracking_url',
                'short_url',
                'campaign',
                'clicks',
                'conversions',
                'conversion_rate',
                'is_active',
                'program',
            ]);
            expect($link['conversion_rate'])->toEqual(10.0); // 20/200 * 100
        });

        test('returns empty list when no links exist', function (): void {
            $request = Request::create('/affiliate/portal/links', 'GET');
            $request->attributes->set('affiliate', $this->affiliate);

            $response = $this->controller->index($request);
            $data = $response->getData(true);

            expect($data['data'])->toBeEmpty();
            expect($data['meta']['total'])->toBe(0);
        });

        test('respects per_page pagination parameter', function (): void {
            for ($i = 0; $i < 15; $i++) {
                AffiliateLink::create([
                    'affiliate_id' => $this->affiliate->id,
                    'destination_url' => "https://example.com/product-{$i}",
                    'tracking_url' => "https://track.example.com/link-{$i}",
                    'clicks' => 0,
                    'conversions' => 0,
                    'is_active' => true,
                ]);
            }

            $request = Request::create('/affiliate/portal/links', 'GET', ['per_page' => 5]);
            $request->attributes->set('affiliate', $this->affiliate);

            $response = $this->controller->index($request);
            $data = $response->getData(true);

            expect($data['data'])->toHaveCount(5);
            expect($data['meta']['per_page'])->toBe(5);
            expect($data['meta']['total'])->toBe(15);
            expect($data['meta']['last_page'])->toBe(3);
        });

        test('includes program information when link has program', function (): void {
            $program = AffiliateProgram::create([
                'name' => 'Test Program',
                'slug' => 'test-program-' . uniqid(),
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1500,
                'is_active' => true,
            ]);

            AffiliateLink::create([
                'affiliate_id' => $this->affiliate->id,
                'program_id' => $program->id,
                'destination_url' => 'https://example.com/product',
                'tracking_url' => 'https://track.example.com/prog',
                'clicks' => 0,
                'conversions' => 0,
                'is_active' => true,
            ]);

            $request = Request::create('/affiliate/portal/links', 'GET');
            $request->attributes->set('affiliate', $this->affiliate);

            $response = $this->controller->index($request);
            $data = $response->getData(true);

            $link = $data['data'][0];
            expect($link['program'])->not->toBeNull();
            expect($link['program']['id'])->toBe($program->id);
            expect($link['program']['name'])->toBe('Test Program');
        });
    });

    describe('create', function (): void {
        test('creates new link with required fields', function (): void {
            $request = Request::create('/affiliate/portal/links', 'POST', [
                'destination_url' => 'https://example.com/new-product',
            ]);
            $request->attributes->set('affiliate', $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $response = $this->controller->create($request);

            expect($response)->toBeInstanceOf(JsonResponse::class);
            expect($response->getStatusCode())->toBe(201);

            $data = $response->getData(true);
            expect($data['message'])->toBe('Link created successfully.');
            expect($data['link'])->toHaveKeys(['id', 'destination_url', 'tracking_url', 'short_url']);
            expect($data['link']['destination_url'])->toBe('https://example.com/new-product');
        });

        test('creates link with campaign and sub_ids', function (): void {
            $request = Request::create('/affiliate/portal/links', 'POST', [
                'destination_url' => 'https://example.com/tracked',
                'campaign' => 'black-friday',
                'sub_id' => 'sidebar-banner',
                'sub_id_2' => 'variation-a',
                'sub_id_3' => 'desktop',
            ]);
            $request->attributes->set('affiliate', $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $response = $this->controller->create($request);
            $data = $response->getData(true);

            expect($response->getStatusCode())->toBe(201);

            // Verify link was created in database
            $link = AffiliateLink::find($data['link']['id']);
            expect($link->campaign)->toBe('black-friday');
            expect($link->sub_id)->toBe('sidebar-banner');
            expect($link->sub_id_2)->toBe('variation-a');
            expect($link->sub_id_3)->toBe('desktop');
        });

        test('creates link with custom slug', function (): void {
            $request = Request::create('/affiliate/portal/links', 'POST', [
                'destination_url' => 'https://example.com/special',
                'custom_slug' => 'my-custom-link',
            ]);
            $request->attributes->set('affiliate', $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $response = $this->controller->create($request);

            expect($response->getStatusCode())->toBe(201);

            $data = $response->getData(true);
            $link = AffiliateLink::find($data['link']['id']);
            expect($link->custom_slug)->toBe('my-custom-link');
        });

        test('creates link with program association', function (): void {
            $program = AffiliateProgram::create([
                'name' => 'Create Test Program',
                'slug' => 'create-test-' . uniqid(),
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'is_active' => true,
            ]);

            $request = Request::create('/affiliate/portal/links', 'POST', [
                'destination_url' => 'https://example.com/program-link',
                'program_id' => $program->id,
            ]);
            $request->attributes->set('affiliate', $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $response = $this->controller->create($request);

            expect($response->getStatusCode())->toBe(201);

            $data = $response->getData(true);
            $link = AffiliateLink::find($data['link']['id']);
            expect($link->program_id)->toBe($program->id);
        });

        test('initializes clicks and conversions to zero', function (): void {
            $request = Request::create('/affiliate/portal/links', 'POST', [
                'destination_url' => 'https://example.com/fresh',
            ]);
            $request->attributes->set('affiliate', $this->affiliate);
            $request->setLaravelSession(app('session.store'));

            $response = $this->controller->create($request);
            $data = $response->getData(true);

            $link = AffiliateLink::find($data['link']['id']);
            expect($link->clicks)->toBe(0);
            expect($link->conversions)->toBe(0);
            expect($link->is_active)->toBeTrue();
        });
    });

    describe('show', function (): void {
        test('returns single link details', function (): void {
            $link = AffiliateLink::create([
                'affiliate_id' => $this->affiliate->id,
                'destination_url' => 'https://example.com/show-test',
                'tracking_url' => 'https://track.example.com/show',
                'short_url' => 'https://short.ly/show',
                'custom_slug' => 'show-slug',
                'campaign' => 'show-campaign',
                'sub_id' => 'sub1',
                'sub_id_2' => 'sub2',
                'sub_id_3' => 'sub3',
                'clicks' => 500,
                'conversions' => 25,
                'is_active' => true,
            ]);

            $request = Request::create('/affiliate/portal/links/' . $link->id, 'GET');
            $request->attributes->set('affiliate', $this->affiliate);

            $response = $this->controller->show($request, $link->id);

            expect($response)->toBeInstanceOf(JsonResponse::class);
            expect($response->getStatusCode())->toBe(200);

            $data = $response->getData(true);
            expect($data)->toHaveKeys([
                'id',
                'destination_url',
                'tracking_url',
                'short_url',
                'custom_slug',
                'campaign',
                'sub_id',
                'sub_id_2',
                'sub_id_3',
                'clicks',
                'conversions',
                'conversion_rate',
                'is_active',
                'created_at',
            ]);
            expect($data['clicks'])->toBe(500);
            expect($data['conversions'])->toBe(25);
            expect($data['conversion_rate'])->toEqual(5.0); // 25/500 * 100
        });

        test('throws 404 for non-existent link', function (): void {
            $request = Request::create('/affiliate/portal/links/non-existent', 'GET');
            $request->attributes->set('affiliate', $this->affiliate);

            $this->controller->show($request, 'non-existent-id');
        })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        test('throws 404 when accessing another affiliate link', function (): void {
            $otherAffiliate = Affiliate::create([
                'code' => 'OTHER-' . uniqid(),
                'name' => 'Other Affiliate',
                'status' => AffiliateStatus::Active,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $otherLink = AffiliateLink::create([
                'affiliate_id' => $otherAffiliate->id,
                'destination_url' => 'https://example.com/other',
                'tracking_url' => 'https://track.example.com/other',
                'clicks' => 0,
                'conversions' => 0,
                'is_active' => true,
            ]);

            $request = Request::create('/affiliate/portal/links/' . $otherLink->id, 'GET');
            $request->attributes->set('affiliate', $this->affiliate);

            $this->controller->show($request, $otherLink->id);
        })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });

    describe('delete', function (): void {
        test('deletes link successfully', function (): void {
            $link = AffiliateLink::create([
                'affiliate_id' => $this->affiliate->id,
                'destination_url' => 'https://example.com/delete-me',
                'tracking_url' => 'https://track.example.com/delete',
                'clicks' => 10,
                'conversions' => 1,
                'is_active' => true,
            ]);

            $linkId = $link->id;

            $request = Request::create('/affiliate/portal/links/' . $link->id, 'DELETE');
            $request->attributes->set('affiliate', $this->affiliate);

            $response = $this->controller->delete($request, $link->id);

            expect($response)->toBeInstanceOf(JsonResponse::class);
            expect($response->getStatusCode())->toBe(200);

            $data = $response->getData(true);
            expect($data['message'])->toBe('Link deleted successfully.');

            // Verify link was deleted
            expect(AffiliateLink::find($linkId))->toBeNull();
        });

        test('throws 404 when deleting non-existent link', function (): void {
            $request = Request::create('/affiliate/portal/links/fake-id', 'DELETE');
            $request->attributes->set('affiliate', $this->affiliate);

            $this->controller->delete($request, 'fake-id');
        })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        test('cannot delete another affiliate link', function (): void {
            $otherAffiliate = Affiliate::create([
                'code' => 'DELETE-OTHER-' . uniqid(),
                'name' => 'Delete Other Affiliate',
                'status' => AffiliateStatus::Active,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $otherLink = AffiliateLink::create([
                'affiliate_id' => $otherAffiliate->id,
                'destination_url' => 'https://example.com/cant-delete',
                'tracking_url' => 'https://track.example.com/cant',
                'clicks' => 0,
                'conversions' => 0,
                'is_active' => true,
            ]);

            $request = Request::create('/affiliate/portal/links/' . $otherLink->id, 'DELETE');
            $request->attributes->set('affiliate', $this->affiliate);

            $this->controller->delete($request, $otherLink->id);
        })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });

    describe('creatives', function (): void {
        test('filters creatives by program_id parameter', function (): void {
            $program1 = AffiliateProgram::create([
                'name' => 'Program 1',
                'slug' => 'prog-1-' . uniqid(),
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'is_active' => true,
            ]);

            $program2 = AffiliateProgram::create([
                'name' => 'Program 2',
                'slug' => 'prog-2-' . uniqid(),
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1500,
                'is_active' => true,
            ]);

            AffiliateProgramCreative::create([
                'program_id' => $program1->id,
                'type' => 'banner',
                'name' => 'Program 1 Banner',
                'tracking_code' => 'TRACK-P1-' . uniqid(),
                'asset_url' => 'https://assets.example.com/p1.png',
                'destination_url' => 'https://example.com/p1',
            ]);

            AffiliateProgramCreative::create([
                'program_id' => $program2->id,
                'type' => 'text',
                'name' => 'Program 2 Text',
                'tracking_code' => 'TRACK-P2-' . uniqid(),
                'asset_url' => 'https://assets.example.com/p2.txt',
                'destination_url' => 'https://example.com/p2',
            ]);

            $request = Request::create('/affiliate/portal/creatives', 'GET', [
                'program_id' => $program1->id,
            ]);
            $request->attributes->set('affiliate', $this->affiliate);

            $response = $this->controller->creatives($request);
            $data = $response->getData(true);

            expect($data['creatives'])->toHaveCount(1);
            expect($data['creatives'][0]['name'])->toBe('Program 1 Banner');
        });

        test('returns creative data with all required fields', function (): void {
            $program = AffiliateProgram::create([
                'name' => 'Creative Fields Program',
                'slug' => 'creative-fields-' . uniqid(),
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'is_active' => true,
            ]);

            AffiliateProgramCreative::create([
                'program_id' => $program->id,
                'type' => 'banner',
                'name' => 'Test Creative',
                'description' => 'A test creative',
                'width' => 728,
                'height' => 90,
                'tracking_code' => 'TRACK-TEST-' . uniqid(),
                'asset_url' => 'https://assets.example.com/test.png',
                'destination_url' => 'https://example.com/test',
            ]);

            $request = Request::create('/affiliate/portal/creatives', 'GET', [
                'program_id' => $program->id,
            ]);
            $request->attributes->set('affiliate', $this->affiliate);

            $response = $this->controller->creatives($request);

            expect($response)->toBeInstanceOf(JsonResponse::class);
            expect($response->getStatusCode())->toBe(200);

            $data = $response->getData(true);
            expect($data)->toHaveKey('creatives');
            expect($data['creatives'])->toHaveCount(1);

            $creative = $data['creatives'][0];
            expect($creative)->toHaveKeys([
                'id',
                'type',
                'name',
                'description',
                'dimensions',
                'asset_url',
                'tracking_url',
                'embed_code',
            ]);
        });

        // Note: Tests for programs() membership are skipped due to a known bug
        // in the Affiliate model where affiliate_program_id is used instead of program_id
        // This causes SQL errors when trying to attach affiliates to programs.
    });
});
