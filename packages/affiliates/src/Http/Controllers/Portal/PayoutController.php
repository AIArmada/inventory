<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers\Portal;

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class PayoutController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $payouts = $affiliate->payouts()
            ->with(['events'])
            ->latest('scheduled_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $payouts->map(fn (AffiliatePayout $payout) => [
                'id' => $payout->id,
                'amount_minor' => $payout->amount_minor,
                'currency' => $payout->currency,
                'status' => $payout->status,
                'scheduled_at' => $payout->scheduled_at?->toIso8601String(),
                'paid_at' => $payout->paid_at?->toIso8601String(),
                'external_reference' => $payout->external_reference,
                'notes' => $payout->notes,
            ]),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $payout = $affiliate->payouts()
            ->with(['events', 'conversions'])
            ->findOrFail($id);

        return response()->json([
            'id' => $payout->id,
            'amount_minor' => $payout->amount_minor,
            'currency' => $payout->currency,
            'status' => $payout->status,
            'scheduled_at' => $payout->scheduled_at?->toIso8601String(),
            'paid_at' => $payout->paid_at?->toIso8601String(),
            'external_reference' => $payout->external_reference,
            'notes' => $payout->notes,
            'events' => $payout->events->map(fn ($event) => [
                'status' => $event->status,
                'notes' => $event->notes,
                'created_at' => $event->created_at->toIso8601String(),
            ]),
            'conversions_count' => $payout->conversions->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $balance = $affiliate->balance;

        $pendingPayouts = $affiliate->payouts()
            ->whereIn('status', [PayoutStatus::Pending->value, PayoutStatus::Processing->value])
            ->sum('total_minor');

        $paidThisYear = $affiliate->payouts()
            ->where('status', PayoutStatus::Completed->value)
            ->where('paid_at', '>=', now()->startOfYear())
            ->sum('total_minor');

        $paidAllTime = $affiliate->payouts()
            ->where('status', PayoutStatus::Completed->value)
            ->sum('total_minor');

        $nextPayout = $affiliate->payouts()
            ->where('status', PayoutStatus::Pending->value)
            ->orderBy('scheduled_at')
            ->first();

        return response()->json([
            'available_balance_minor' => $balance?->available_minor ?? 0,
            'holding_balance_minor' => $balance?->holding_minor ?? 0,
            'pending_payouts_minor' => $pendingPayouts,
            'paid_this_year_minor' => $paidThisYear,
            'paid_all_time_minor' => $paidAllTime,
            'next_payout' => $nextPayout ? [
                'id' => $nextPayout->id,
                'amount_minor' => $nextPayout->amount_minor,
                'scheduled_at' => $nextPayout->scheduled_at?->toIso8601String(),
            ] : null,
            'currency' => $affiliate->currency,
        ]);
    }
}
