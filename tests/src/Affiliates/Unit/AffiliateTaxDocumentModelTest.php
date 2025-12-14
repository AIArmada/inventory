<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('AffiliateTaxDocument Model', function (): void {
    it('can be created with required fields', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TAX-TEST-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $doc = AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099',
            'tax_year' => 2024,
            'status' => 'pending',
            'total_amount_minor' => 500000,
            'currency' => 'USD',
        ]);

        expect($doc)->toBeInstanceOf(AffiliateTaxDocument::class)
            ->and($doc->document_type)->toBe('1099')
            ->and($doc->tax_year)->toBe(2024)
            ->and($doc->status)->toBe('pending')
            ->and($doc->total_amount_minor)->toBe(500000);
    });

    it('belongs to an affiliate', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TAX-AFF-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $doc = AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099',
            'tax_year' => 2023,
            'status' => 'generated',
            'total_amount_minor' => 300000,
            'currency' => 'USD',
        ]);

        expect($doc->affiliate())->toBeInstanceOf(BelongsTo::class)
            ->and($doc->affiliate->id)->toBe($affiliate->id);
    });

    it('stores document path', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TAX-PATH-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $doc = AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099',
            'tax_year' => 2024,
            'status' => 'generated',
            'total_amount_minor' => 250000,
            'currency' => 'USD',
            'document_path' => 'tax-documents/2024/1099-affiliate-123.pdf',
        ]);

        expect($doc->document_path)->toBe('tax-documents/2024/1099-affiliate-123.pdf');
    });

    it('tracks generated_at timestamp', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TAX-GEN-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $doc = AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099',
            'tax_year' => 2024,
            'status' => 'generated',
            'total_amount_minor' => 400000,
            'currency' => 'USD',
            'generated_at' => '2025-01-15 10:30:00',
        ]);

        expect($doc->generated_at)->toBeInstanceOf(Illuminate\Support\Carbon::class)
            ->and($doc->generated_at->format('Y-m-d'))->toBe('2025-01-15');
    });

    it('tracks sent_at timestamp', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TAX-SENT-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $doc = AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099',
            'tax_year' => 2024,
            'status' => 'sent',
            'total_amount_minor' => 350000,
            'currency' => 'USD',
            'generated_at' => '2025-01-15 10:30:00',
            'sent_at' => '2025-01-16 09:00:00',
        ]);

        expect($doc->sent_at)->toBeInstanceOf(Illuminate\Support\Carbon::class)
            ->and($doc->sent_at->format('Y-m-d'))->toBe('2025-01-16');
    });

    it('supports different document types', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TAX-TYPES-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $doc1099 = AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099',
            'tax_year' => 2024,
            'status' => 'pending',
            'total_amount_minor' => 500000,
            'currency' => 'USD',
        ]);

        $docW9 = AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => 'W9',
            'tax_year' => 2024,
            'status' => 'pending',
            'total_amount_minor' => 0,
            'currency' => 'USD',
        ]);

        expect($doc1099->document_type)->toBe('1099')
            ->and($docW9->document_type)->toBe('W9');
    });

    it('supports different statuses', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TAX-STATUS-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $pendingDoc = AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099',
            'tax_year' => 2024,
            'status' => 'pending',
            'total_amount_minor' => 100000,
            'currency' => 'USD',
        ]);

        $sentDoc = AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099',
            'tax_year' => 2023,
            'status' => 'sent',
            'total_amount_minor' => 200000,
            'currency' => 'USD',
        ]);

        expect($pendingDoc->status)->toBe('pending')
            ->and($sentDoc->status)->toBe('sent');
    });

    it('casts numeric fields as integers', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TAX-CAST-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $doc = AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099',
            'tax_year' => '2024',
            'status' => 'pending',
            'total_amount_minor' => '600000',
            'currency' => 'USD',
        ]);

        expect($doc->tax_year)->toBeInt()
            ->and($doc->total_amount_minor)->toBeInt();
    });

    it('stores notes', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TAX-NOTE-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $doc = AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099',
            'tax_year' => 2024,
            'status' => 'pending',
            'total_amount_minor' => 450000,
            'currency' => 'USD',
            'notes' => 'Requires manual review due to address change',
        ]);

        expect($doc->notes)->toBe('Requires manual review due to address change');
    });
});
