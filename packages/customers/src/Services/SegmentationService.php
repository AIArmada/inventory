<?php

declare(strict_types=1);

namespace AIArmada\Customers\Services;

use AIArmada\Customers\Events\CustomerSegmentChanged;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Service for managing customer segmentation.
 *
 * Provides methods for:
 * - Automatically segmenting customers based on rules
 * - Manually managing segment membership
 * - Bulk rebuilding segment memberships
 */
class SegmentationService
{
    /**
     * Rebuild all automatic segments.
     *
     * @return array<string, int> Map of segment names to customer counts
     */
    public function rebuildAllSegments(?Model $owner = null): array
    {
        $query = Segment::query()->active()->automatic();

        if ($owner) {
            $query->forOwner($owner);
        }

        $results = [];

        $query->each(function (Segment $segment) use (&$results): void {
            $count = $this->rebuildSegment($segment);
            $results[$segment->name] = $count;
        });

        return $results;
    }

    /**
     * Rebuild a single segment's customer list.
     */
    public function rebuildSegment(Segment $segment): int
    {
        if (! $segment->is_automatic) {
            return $segment->customers()->count();
        }

        $matchingCustomers = $segment->getMatchingCustomers();
        $currentCustomerIds = $segment->customers()->pluck('id')->toArray();
        $newCustomerIds = $matchingCustomers->pluck('id')->toArray();

        // Find customers added and removed
        $addedIds = array_diff($newCustomerIds, $currentCustomerIds);
        $removedIds = array_diff($currentCustomerIds, $newCustomerIds);

        // Sync the segment
        $segment->customers()->sync($newCustomerIds);

        // Fire events for changes
        foreach ($addedIds as $customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                event(new CustomerSegmentChanged($customer, $segment, 'added'));
            }
        }

        foreach ($removedIds as $customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                event(new CustomerSegmentChanged($customer, $segment, 'removed'));
            }
        }

        return count($newCustomerIds);
    }

    /**
     * Evaluate and update segment memberships for a single customer.
     *
     * @return Collection<int, Segment> Segments the customer now belongs to
     */
    public function evaluateCustomer(Customer $customer): Collection
    {
        $automaticSegments = Segment::query()
            ->active()
            ->automatic()
            ->forOwner($customer->owner)
            ->get();

        $matchingSegments = collect();

        foreach ($automaticSegments as $segment) {
            if ($this->customerMatchesSegment($customer, $segment)) {
                $matchingSegments->push($segment);
            }
        }

        // Get current automatic segments
        $currentAutoSegmentIds = $customer->segments()
            ->where('is_automatic', true)
            ->pluck('id')
            ->toArray();

        $newAutoSegmentIds = $matchingSegments->pluck('id')->toArray();

        // Sync only automatic segments (preserve manual ones)
        $manualSegmentIds = $customer->segments()
            ->where('is_automatic', false)
            ->pluck('id')
            ->toArray();

        $allSegmentIds = array_merge($manualSegmentIds, $newAutoSegmentIds);
        $customer->segments()->sync($allSegmentIds);

        // Fire events for changes
        $addedIds = array_diff($newAutoSegmentIds, $currentAutoSegmentIds);
        $removedIds = array_diff($currentAutoSegmentIds, $newAutoSegmentIds);

        foreach ($addedIds as $segmentId) {
            $segment = Segment::find($segmentId);
            if ($segment) {
                event(new CustomerSegmentChanged($customer, $segment, 'added'));
            }
        }

        foreach ($removedIds as $segmentId) {
            $segment = Segment::find($segmentId);
            if ($segment) {
                event(new CustomerSegmentChanged($customer, $segment, 'removed'));
            }
        }

        return $matchingSegments;
    }

    /**
     * Check if a customer matches a segment's conditions.
     */
    public function customerMatchesSegment(Customer $customer, Segment $segment): bool
    {
        $conditions = $segment->conditions ?? [];

        if (empty($conditions)) {
            return false;
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateCondition($customer, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add a customer to a manual segment.
     */
    public function addToSegment(Customer $customer, Segment $segment): bool
    {
        if ($customer->segments()->where('segment_id', $segment->id)->exists()) {
            return false;
        }

        $customer->segments()->attach($segment->id);
        event(new CustomerSegmentChanged($customer, $segment, 'added'));

        return true;
    }

    /**
     * Remove a customer from a segment.
     */
    public function removeFromSegment(Customer $customer, Segment $segment): bool
    {
        if (! $customer->segments()->where('segment_id', $segment->id)->exists()) {
            return false;
        }

        $customer->segments()->detach($segment->id);
        event(new CustomerSegmentChanged($customer, $segment, 'removed'));

        return true;
    }

    /**
     * Get segment statistics.
     *
     * @return array<string, mixed>
     */
    public function getSegmentStats(Segment $segment): array
    {
        $customers = $segment->customers;

        if ($customers->isEmpty()) {
            return [
                'customer_count' => 0,
                'total_lifetime_value' => 0,
                'average_lifetime_value' => 0,
                'total_orders' => 0,
                'average_orders' => 0,
                'marketing_opted_in' => 0,
                'marketing_opted_in_percentage' => 0,
            ];
        }

        $totalLtv = $customers->sum('lifetime_value');
        $totalOrders = $customers->sum('total_orders');
        $marketingOptedIn = $customers->where('accepts_marketing', true)->count();

        return [
            'customer_count' => $customers->count(),
            'total_lifetime_value' => $totalLtv,
            'average_lifetime_value' => (int) ($totalLtv / $customers->count()),
            'total_orders' => $totalOrders,
            'average_orders' => round($totalOrders / $customers->count(), 2),
            'marketing_opted_in' => $marketingOptedIn,
            'marketing_opted_in_percentage' => round(($marketingOptedIn / $customers->count()) * 100, 1),
        ];
    }

    /**
     * Evaluate a single condition against a customer.
     *
     * @param  array<string, mixed>  $condition
     */
    protected function evaluateCondition(Customer $customer, array $condition): bool
    {
        $field = $condition['field'] ?? null;
        $value = $condition['value'] ?? null;

        if (! $field || $value === null) {
            return true;
        }

        return match ($field) {
            'lifetime_value_min' => $customer->lifetime_value >= $value,
            'lifetime_value_max' => $customer->lifetime_value <= $value,
            'total_orders_min' => $customer->total_orders >= $value,
            'total_orders_max' => $customer->total_orders <= $value,
            'last_order_days' => $customer->last_order_at && $customer->last_order_at->gte(now()->subDays($value)),
            'no_order_days' => ! $customer->last_order_at || $customer->last_order_at->lte(now()->subDays($value)),
            'accepts_marketing' => $customer->accepts_marketing === (bool) $value,
            'is_tax_exempt' => $customer->is_tax_exempt === (bool) $value,
            'status' => $customer->status->value === $value,
            default => data_get($customer, $field) === $value,
        };
    }
}
