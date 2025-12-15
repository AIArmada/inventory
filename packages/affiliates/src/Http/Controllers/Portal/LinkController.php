<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers\Portal;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

final class LinkController extends Controller
{
    public function __construct(
        private readonly AffiliateLinkGenerator $linkGenerator
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $links = $affiliate->links()
            ->with('program')
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $links->map(fn (AffiliateLink $link) => [
                'id' => $link->id,
                'destination_url' => $link->destination_url,
                'tracking_url' => $link->tracking_url,
                'short_url' => $link->short_url,
                'campaign' => $link->campaign,
                'clicks' => $link->clicks,
                'conversions' => $link->conversions,
                'conversion_rate' => $link->getConversionRate(),
                'is_active' => $link->is_active,
                'program' => $link->program ? [
                    'id' => $link->program->id,
                    'name' => $link->program->name,
                ] : null,
            ]),
            'meta' => [
                'current_page' => $links->currentPage(),
                'last_page' => $links->lastPage(),
                'per_page' => $links->perPage(),
                'total' => $links->total(),
            ],
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $validated = $request->validate([
            'destination_url' => 'required|url|max:2048',
            'program_id' => [
                'nullable',
                'uuid',
                Rule::exists(config('affiliates.table_names.programs', 'affiliate_programs'), 'id'),
            ],
            'campaign' => 'nullable|string|max:100',
            'sub_id' => 'nullable|string|max:100',
            'sub_id_2' => 'nullable|string|max:100',
            'sub_id_3' => 'nullable|string|max:100',
            'custom_slug' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique(config('affiliates.table_names.links', 'affiliate_links'), 'custom_slug'),
            ],
        ]);

        $trackingUrl = $this->linkGenerator->generate($affiliate->code, $validated['destination_url'], [
            'campaign' => $validated['campaign'] ?? null,
            'sub_id' => $validated['sub_id'] ?? null,
        ]);

        $link = AffiliateLink::create([
            'affiliate_id' => $affiliate->id,
            'program_id' => $validated['program_id'] ?? null,
            'destination_url' => $validated['destination_url'],
            'tracking_url' => $trackingUrl,
            'custom_slug' => $validated['custom_slug'] ?? null,
            'campaign' => $validated['campaign'] ?? null,
            'sub_id' => $validated['sub_id'] ?? null,
            'sub_id_2' => $validated['sub_id_2'] ?? null,
            'sub_id_3' => $validated['sub_id_3'] ?? null,
            'clicks' => 0,
            'conversions' => 0,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Link created successfully.',
            'link' => [
                'id' => $link->id,
                'destination_url' => $link->destination_url,
                'tracking_url' => $link->tracking_url,
                'short_url' => $link->short_url,
            ],
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $link = $affiliate->links()->findOrFail($id);

        return response()->json([
            'id' => $link->id,
            'destination_url' => $link->destination_url,
            'tracking_url' => $link->tracking_url,
            'short_url' => $link->short_url,
            'custom_slug' => $link->custom_slug,
            'campaign' => $link->campaign,
            'sub_id' => $link->sub_id,
            'sub_id_2' => $link->sub_id_2,
            'sub_id_3' => $link->sub_id_3,
            'clicks' => $link->clicks,
            'conversions' => $link->conversions,
            'conversion_rate' => $link->getConversionRate(),
            'is_active' => $link->is_active,
            'created_at' => $link->created_at->toIso8601String(),
        ]);
    }

    public function delete(Request $request, string $id): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $link = $affiliate->links()->findOrFail($id);
        $link->delete();

        return response()->json([
            'message' => 'Link deleted successfully.',
        ]);
    }

    public function creatives(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $programId = $request->get('program_id');

        $query = AffiliateProgramCreative::query();

        if ($programId) {
            $query->where('program_id', $programId);
        } else {
            // Get creatives from programs the affiliate is a member of
            $programIds = $affiliate->programs()->pluck('id');
            $query->whereIn('program_id', $programIds);
        }

        $creatives = $query->get();

        return response()->json([
            'creatives' => $creatives->map(fn (AffiliateProgramCreative $creative) => [
                'id' => $creative->id,
                'type' => $creative->type,
                'name' => $creative->name,
                'description' => $creative->description,
                'dimensions' => $creative->getDimensions(),
                'asset_url' => $creative->asset_url,
                'tracking_url' => $creative->getTrackingUrl($affiliate),
                'embed_code' => $creative->getEmbedCode($affiliate),
            ]),
        ]);
    }
}
