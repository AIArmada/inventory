<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\RegistrationApprovalMode;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AffiliateRegistrationService
{
    /**
     * Register a new affiliate.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, ?Model $owner = null): Affiliate
    {
        $approvalMode = $this->getApprovalMode();
        $status = $this->determineStatus($data, $approvalMode);
        $commissionType = $this->getDefaultCommissionType();
        $commissionRate = $this->getDefaultCommissionRate();

        $affiliate = new Affiliate([
            'code' => $data['code'] ?? $this->generateCode($data['name'] ?? ''),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $status,
            'commission_type' => $data['commission_type'] ?? $commissionType,
            'commission_rate' => $data['commission_rate'] ?? $commissionRate,
            'currency' => $data['currency'] ?? config('affiliates.currency.default', 'USD'),
            'contact_email' => $data['contact_email'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);

        if ($owner) {
            $affiliate->owner_type = $owner->getMorphClass();
            $affiliate->owner_id = $owner->getKey();
        }

        if ($status === AffiliateStatus::Active) {
            $affiliate->activated_at = now();
        }

        $affiliate->save();

        return $affiliate;
    }

    /**
     * Approve a pending affiliate.
     */
    public function approve(Affiliate $affiliate): Affiliate
    {
        if ($affiliate->status === AffiliateStatus::Active) {
            return $affiliate;
        }

        $affiliate->status = AffiliateStatus::Active;
        $affiliate->activated_at = now();
        $affiliate->save();

        return $affiliate;
    }

    /**
     * Reject a pending affiliate.
     */
    public function reject(Affiliate $affiliate): Affiliate
    {
        $affiliate->status = AffiliateStatus::Disabled;
        $affiliate->save();

        return $affiliate;
    }

    /**
     * Check if registration is enabled.
     */
    public function isRegistrationEnabled(): bool
    {
        return (bool) config('affiliates.registration.enabled', true);
    }

    /**
     * Get the approval mode.
     */
    public function getApprovalMode(): RegistrationApprovalMode
    {
        $mode = config('affiliates.registration.approval_mode', 'admin');

        return RegistrationApprovalMode::tryFrom($mode) ?? RegistrationApprovalMode::Admin;
    }

    /**
     * Determine the initial status based on approval mode.
     *
     * @param  array<string, mixed>  $data
     */
    private function determineStatus(array $data, RegistrationApprovalMode $approvalMode): AffiliateStatus
    {
        if (isset($data['status'])) {
            return $data['status'] instanceof AffiliateStatus
                ? $data['status']
                : (AffiliateStatus::tryFrom($data['status']) ?? $approvalMode->defaultStatus());
        }

        return $approvalMode->defaultStatus();
    }

    /**
     * Get the default commission type.
     */
    private function getDefaultCommissionType(): CommissionType
    {
        $type = config('affiliates.registration.default_commission_type', 'percentage');

        return CommissionType::tryFrom($type) ?? CommissionType::Percentage;
    }

    /**
     * Get the default commission rate.
     */
    private function getDefaultCommissionRate(): int
    {
        return (int) config('affiliates.registration.default_commission_rate', 1000);
    }

    /**
     * Generate a unique affiliate code.
     */
    private function generateCode(string $name = ''): string
    {
        $slug = Str::slug($name, '');
        $base = ($slug !== '' && mb_strlen($slug) > 0)
            ? Str::upper(Str::substr($slug, 0, 6))
            : 'AFF';

        // Ensure base is not empty after all transformations
        if ($base === '' || mb_strlen($base) === 0) {
            $base = 'AFF';
        }

        $suffix = Str::upper(Str::random(4));

        $code = $base.$suffix;

        while (Affiliate::where('code', $code)->exists()) {
            $suffix = Str::upper(Str::random(4));
            $code = $base.$suffix;
        }

        return $code;
    }
}
