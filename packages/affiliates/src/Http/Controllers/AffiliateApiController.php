<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers;

use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

final class AffiliateApiController extends Controller
{
    public function __construct(
        private readonly AffiliateService $affiliates,
        private readonly AffiliateReportService $reports,
        private readonly AffiliateLinkGenerator $links
    ) {}

    public function summary(string $code): JsonResponse
    {
        $affiliate = $this->affiliates->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        return response()->json($this->reports->affiliateSummary($affiliate->getKey()));
    }

    public function links(string $code, Request $request): JsonResponse
    {
        $affiliate = $this->affiliates->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        $url = (string) $request->query('url', url('/'));
        $ttl = $request->integer('ttl', null);
        $params = (array) $request->query('params', []);

        try {
            $link = $this->links->generate($affiliate->code, $url, $params, $ttl ?: null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['link' => $link]);
    }

    public function creatives(string $code): JsonResponse
    {
        $affiliate = $this->affiliates->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        $creatives = $affiliate->metadata['creatives'] ?? [];

        return response()->json([
            'creatives' => $creatives,
        ]);
    }
}
