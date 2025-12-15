<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers\Portal;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly PayoutProcessorFactory $processorFactory
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        return response()->json([
            'id' => $affiliate->id,
            'name' => $affiliate->name,
            'email' => $affiliate->email,
            'code' => $affiliate->code,
            'status' => $affiliate->status->value,
            'commission_type' => $affiliate->commission_type->value,
            'commission_rate_basis_points' => $affiliate->commission_rate_basis_points,
            'currency' => $affiliate->currency,
            'rank' => $affiliate->rank ? [
                'id' => $affiliate->rank->id,
                'name' => $affiliate->rank->name,
                'level' => $affiliate->rank->level,
            ] : null,
            'joined_at' => $affiliate->created_at->toIso8601String(),
            'metadata' => $affiliate->metadata,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'metadata' => 'sometimes|array',
        ]);

        $attributes = array_intersect_key($validated, array_flip(['name', 'metadata']));

        if (array_key_exists('email', $validated)) {
            $attributes['contact_email'] = $validated['email'];
        }

        if ($attributes !== []) {
            $affiliate->update($attributes);
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'affiliate' => [
                'id' => $affiliate->id,
                'name' => $affiliate->name,
                'email' => $affiliate->email,
            ],
        ]);
    }

    public function payoutMethods(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $methods = $affiliate->payoutMethods()
            ->get()
            ->map(fn (AffiliatePayoutMethod $method) => [
                'id' => $method->id,
                'type' => $method->type->value,
                'label' => $method->label,
                'is_default' => $method->is_default,
                'is_verified' => $method->is_verified,
                'created_at' => $method->created_at->toIso8601String(),
            ]);

        return response()->json([
            'payout_methods' => $methods,
            'available_types' => $this->processorFactory->getAvailableProcessors(),
        ]);
    }

    public function addPayoutMethod(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $validated = $request->validate([
            'type' => 'required|string',
            'label' => 'required|string|max:255',
            'details' => 'required|array',
            'is_default' => 'boolean',
        ]);

        // Validate details based on processor type
        if ($this->processorFactory->hasProcessor($validated['type'])) {
            $processor = $this->processorFactory->make($validated['type']);
            $errors = $processor->validateDetails($validated['details']);

            if (! empty($errors)) {
                throw ValidationException::withMessages($errors);
            }
        }

        // If setting as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            $affiliate->payoutMethods()->update(['is_default' => false]);
        }

        $method = $affiliate->payoutMethods()->create([
            'type' => $validated['type'],
            'label' => $validated['label'],
            'details' => $validated['details'],
            'is_default' => $validated['is_default'] ?? false,
            'is_verified' => false,
        ]);

        return response()->json([
            'message' => 'Payout method added successfully.',
            'payout_method' => [
                'id' => $method->id,
                'type' => $method->type->value,
                'label' => $method->label,
                'is_default' => $method->is_default,
            ],
        ], 201);
    }

    public function removePayoutMethod(Request $request, string $id): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $method = $affiliate->payoutMethods()->findOrFail($id);
        $method->delete();

        return response()->json([
            'message' => 'Payout method removed successfully.',
        ]);
    }

    public function setDefaultPayoutMethod(Request $request, string $id): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $affiliate->payoutMethods()->update(['is_default' => false]);

        $method = $affiliate->payoutMethods()->findOrFail($id);
        $method->update(['is_default' => true]);

        return response()->json([
            'message' => 'Default payout method updated.',
        ]);
    }
}
