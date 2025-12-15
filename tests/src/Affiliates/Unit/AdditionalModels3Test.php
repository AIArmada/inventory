<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateCommissionTemplate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Models\AffiliateTrainingModule;
use AIArmada\Affiliates\Models\AffiliateTrainingProgress;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// AffiliateProgramCreative Tests
test('AffiliateProgramCreative can be created with required fields', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Creative Test Program',
        'slug' => 'creative-test-program',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $creative = AffiliateProgramCreative::create([
        'program_id' => $program->id,
        'type' => 'banner',
        'name' => 'Test Banner',
        'asset_url' => 'https://cdn.example.com/banner.png',
        'destination_url' => 'https://example.com/landing',
        'tracking_code' => 'BANNER_001',
        'width' => 300,
        'height' => 250,
    ]);

    expect($creative)->toBeInstanceOf(AffiliateProgramCreative::class);
    expect($creative->name)->toBe('Test Banner');
});

test('AffiliateProgramCreative has program relationship', function (): void {
    $creative = new AffiliateProgramCreative;

    expect($creative->program())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateProgramCreative getTrackingUrl appends affiliate code', function (): void {
    config(['affiliates.links.parameter' => 'ref']);

    $affiliate = Affiliate::create([
        'code' => 'CREAT001',
        'name' => 'Creative Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $creative = new AffiliateProgramCreative([
        'destination_url' => 'https://example.com/landing',
    ]);

    $url = $creative->getTrackingUrl($affiliate);

    expect($url)->toBe('https://example.com/landing?ref=CREAT001');
});

test('AffiliateProgramCreative getTrackingUrl uses ampersand for existing query string', function (): void {
    config(['affiliates.links.parameter' => 'ref']);

    $affiliate = Affiliate::create([
        'code' => 'CREAT002',
        'name' => 'Query Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $creative = new AffiliateProgramCreative([
        'destination_url' => 'https://example.com/landing?source=email',
    ]);

    $url = $creative->getTrackingUrl($affiliate);

    expect($url)->toBe('https://example.com/landing?source=email&ref=CREAT002');
});

test('AffiliateProgramCreative getEmbedCode for banner type', function (): void {
    config(['affiliates.links.parameter' => 'ref']);

    $affiliate = Affiliate::create([
        'code' => 'EMBED001',
        'name' => 'Embed Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $creative = new AffiliateProgramCreative([
        'type' => 'banner',
        'name' => 'Test Banner',
        'asset_url' => 'https://cdn.example.com/banner.png',
        'destination_url' => 'https://example.com/landing',
        'width' => 300,
        'height' => 250,
    ]);

    $embedCode = $creative->getEmbedCode($affiliate);

    expect($embedCode)->toContain('<a href="');
    expect($embedCode)->toContain('<img src="');
    expect($embedCode)->toContain('width="300"');
    expect($embedCode)->toContain('height="250"');
});

test('AffiliateProgramCreative getEmbedCode for text link type', function (): void {
    config(['affiliates.links.parameter' => 'ref']);

    $affiliate = Affiliate::create([
        'code' => 'EMBED002',
        'name' => 'Text Link Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $creative = new AffiliateProgramCreative([
        'type' => 'text_link',
        'name' => 'Click Here!',
        'destination_url' => 'https://example.com/landing',
    ]);

    $embedCode = $creative->getEmbedCode($affiliate);

    expect($embedCode)->toContain('<a href="');
    expect($embedCode)->toContain('Click Here!</a>');
});

test('AffiliateProgramCreative getDimensions returns formatted string', function (): void {
    $creative = new AffiliateProgramCreative([
        'width' => 300,
        'height' => 250,
    ]);

    expect($creative->getDimensions())->toBe('300x250');
});

test('AffiliateProgramCreative getDimensions returns null when dimensions missing', function (): void {
    $creative = new AffiliateProgramCreative([
        'width' => null,
        'height' => null,
    ]);

    expect($creative->getDimensions())->toBeNull();
});

// AffiliateCommissionTemplate Tests
test('AffiliateCommissionTemplate can be created with required fields', function (): void {
    $template = AffiliateCommissionTemplate::create([
        'name' => 'Standard Commission',
        'slug' => 'standard-commission',
        'is_default' => false,
        'is_active' => true,
        'rules' => [
            'commission_rules' => [
                ['type' => 'affiliate', 'commission_type' => 'percentage', 'rate' => 1000, 'conditions' => []],
            ],
            'volume_tiers' => [],
            'mlm_overrides' => [],
        ],
    ]);

    expect($template)->toBeInstanceOf(AffiliateCommissionTemplate::class);
    expect($template->name)->toBe('Standard Commission');
});

test('AffiliateCommissionTemplate createStandardPercentage creates correct template', function (): void {
    $template = AffiliateCommissionTemplate::createStandardPercentage('10% Commission', 1000, false);

    expect($template->name)->toBe('10% Commission');
    expect($template->rules['commission_rules'])->toHaveCount(1);
    expect($template->rules['commission_rules'][0]['rate'])->toBe(1000);
});

test('AffiliateCommissionTemplate createTieredVolume creates correct template', function (): void {
    $template = AffiliateCommissionTemplate::createTieredVolume('Tiered Volume', 500);

    expect($template->name)->toBe('Tiered Volume');
    expect($template->rules['volume_tiers'])->not->toBeEmpty();
    expect($template->rules['commission_rules'][0]['rate'])->toBe(500);
});

test('AffiliateCommissionTemplate createMlm creates correct template', function (): void {
    $template = AffiliateCommissionTemplate::createMlm('MLM Structure', 1000, [50, 25, 10]);

    expect($template->name)->toBe('MLM Structure');
    expect($template->rules['mlm_overrides'])->toBe([1 => 50, 2 => 25, 3 => 10]);
});

test('AffiliateCommissionTemplate getCommissionRules returns rules', function (): void {
    $template = new AffiliateCommissionTemplate([
        'rules' => [
            'commission_rules' => [
                ['type' => 'affiliate', 'rate' => 1000],
            ],
        ],
    ]);

    $rules = $template->getCommissionRules();

    expect($rules)->toHaveCount(1);
    expect($rules[0]['rate'])->toBe(1000);
});

test('AffiliateCommissionTemplate getVolumeTiers returns tiers', function (): void {
    $template = new AffiliateCommissionTemplate([
        'rules' => [
            'volume_tiers' => [
                ['min_volume' => 0, 'max_volume' => 10000, 'bonus_rate' => 0],
                ['min_volume' => 10001, 'max_volume' => null, 'bonus_rate' => 100],
            ],
        ],
    ]);

    $tiers = $template->getVolumeTiers();

    expect($tiers)->toHaveCount(2);
});

test('AffiliateCommissionTemplate getMlmOverrides returns overrides', function (): void {
    $template = new AffiliateCommissionTemplate([
        'rules' => [
            'mlm_overrides' => [1 => 50, 2 => 25],
        ],
    ]);

    $overrides = $template->getMlmOverrides();

    expect($overrides)->toBe([1 => 50, 2 => 25]);
});

test('AffiliateCommissionTemplate scopeActive filters correctly', function (): void {
    AffiliateCommissionTemplate::create([
        'name' => 'Active Template',
        'slug' => 'active-template',
        'is_active' => true,
        'rules' => [],
    ]);

    AffiliateCommissionTemplate::create([
        'name' => 'Inactive Template',
        'slug' => 'inactive-template',
        'is_active' => false,
        'rules' => [],
    ]);

    $activeTemplates = AffiliateCommissionTemplate::active()->pluck('slug');

    expect($activeTemplates)->toContain('active-template');
    expect($activeTemplates)->not->toContain('inactive-template');
});

test('AffiliateCommissionTemplate scopeDefault filters correctly', function (): void {
    AffiliateCommissionTemplate::create([
        'name' => 'Default Template',
        'slug' => 'default-template',
        'is_default' => true,
        'is_active' => true,
        'rules' => [],
    ]);

    AffiliateCommissionTemplate::create([
        'name' => 'Non-Default Template',
        'slug' => 'non-default-template',
        'is_default' => false,
        'is_active' => true,
        'rules' => [],
    ]);

    $defaultTemplates = AffiliateCommissionTemplate::default()->pluck('slug');

    expect($defaultTemplates)->toContain('default-template');
    expect($defaultTemplates)->not->toContain('non-default-template');
});

test('AffiliateCommissionTemplate getDefault returns default template', function (): void {
    AffiliateCommissionTemplate::create([
        'name' => 'The Default',
        'slug' => 'the-default',
        'is_default' => true,
        'is_active' => true,
        'rules' => [],
    ]);

    $default = AffiliateCommissionTemplate::getDefault();

    expect($default)->not->toBeNull();
    expect($default->slug)->toBe('the-default');
});

// AffiliateVolumeTier Tests
test('AffiliateVolumeTier can be created with required fields', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Volume Tier Program',
        'slug' => 'volume-tier-program',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $tier = AffiliateVolumeTier::create([
        'program_id' => $program->id,
        'name' => 'Silver Tier',
        'min_volume_minor' => 100000,
        'max_volume_minor' => 500000,
        'commission_rate_basis_points' => 1200,
        'period' => 'monthly',
    ]);

    expect($tier)->toBeInstanceOf(AffiliateVolumeTier::class);
    expect($tier->name)->toBe('Silver Tier');
});

test('AffiliateVolumeTier has program relationship', function (): void {
    $tier = new AffiliateVolumeTier;

    expect($tier->program())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateVolumeTier containsVolume returns true when within range', function (): void {
    $tier = new AffiliateVolumeTier([
        'min_volume_minor' => 100000,
        'max_volume_minor' => 500000,
    ]);

    expect($tier->containsVolume(250000))->toBeTrue();
});

test('AffiliateVolumeTier containsVolume returns false when below min', function (): void {
    $tier = new AffiliateVolumeTier([
        'min_volume_minor' => 100000,
        'max_volume_minor' => 500000,
    ]);

    expect($tier->containsVolume(50000))->toBeFalse();
});

test('AffiliateVolumeTier containsVolume returns false when above max', function (): void {
    $tier = new AffiliateVolumeTier([
        'min_volume_minor' => 100000,
        'max_volume_minor' => 500000,
    ]);

    expect($tier->containsVolume(600000))->toBeFalse();
});

test('AffiliateVolumeTier containsVolume returns true when no max (unlimited)', function (): void {
    $tier = new AffiliateVolumeTier([
        'min_volume_minor' => 100000,
        'max_volume_minor' => null,
    ]);

    expect($tier->containsVolume(999999999))->toBeTrue();
});

test('AffiliateVolumeTier getCommissionRatePercentage returns correct percentage', function (): void {
    $tier = new AffiliateVolumeTier([
        'commission_rate_basis_points' => 1200,
    ]);

    expect($tier->getCommissionRatePercentage())->toBe(12.0);
});

// AffiliateSupportTicket Tests
test('AffiliateSupportTicket can be created', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'SUPPORT001',
        'name' => 'Support Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $ticket = AffiliateSupportTicket::create([
        'affiliate_id' => $affiliate->id,
        'subject' => 'Help needed',
        'status' => 'open',
    ]);

    expect($ticket)->toBeInstanceOf(AffiliateSupportTicket::class);
    expect($ticket->subject)->toBe('Help needed');
});

test('AffiliateSupportTicket has affiliate relationship', function (): void {
    $ticket = new AffiliateSupportTicket;

    expect($ticket->affiliate())->toBeInstanceOf(BelongsTo::class);
});

// AffiliateSupportMessage Tests
test('AffiliateSupportMessage can be created', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'MSG001',
        'name' => 'Message Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $ticket = AffiliateSupportTicket::create([
        'affiliate_id' => $affiliate->id,
        'subject' => 'Test ticket',
        'status' => 'open',
    ]);

    $message = AffiliateSupportMessage::create([
        'ticket_id' => $ticket->id,
        'message' => 'This is the message content',
        'is_staff_reply' => false,
    ]);

    expect($message)->toBeInstanceOf(AffiliateSupportMessage::class);
    expect($message->message)->toBe('This is the message content');
});

test('AffiliateSupportMessage has ticket relationship', function (): void {
    $message = new AffiliateSupportMessage;

    expect($message->ticket())->toBeInstanceOf(BelongsTo::class);
});

// AffiliateTaxDocument Tests
test('AffiliateTaxDocument can be created', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'TAX001',
        'name' => 'Tax Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $doc = AffiliateTaxDocument::create([
        'affiliate_id' => $affiliate->id,
        'document_type' => '1099',
        'tax_year' => 2024,
        'status' => 'generated',
        'total_amount_minor' => 100000,
        'currency' => 'USD',
    ]);

    expect($doc)->toBeInstanceOf(AffiliateTaxDocument::class);
    expect($doc->tax_year)->toBe(2024);
});

test('AffiliateTaxDocument has affiliate relationship', function (): void {
    $doc = new AffiliateTaxDocument;

    expect($doc->affiliate())->toBeInstanceOf(BelongsTo::class);
});

// AffiliateTrainingModule Tests
test('AffiliateTrainingModule can be created', function (): void {
    $module = AffiliateTrainingModule::create([
        'title' => 'Getting Started',
        'slug' => 'getting-started',
        'content' => 'Welcome to the affiliate program!',
        'order' => 1,
    ]);

    expect($module)->toBeInstanceOf(AffiliateTrainingModule::class);
    expect($module->title)->toBe('Getting Started');
});

// AffiliateTrainingProgress Tests
test('AffiliateTrainingProgress can be created', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'TRAIN001',
        'name' => 'Training Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $module = AffiliateTrainingModule::create([
        'title' => 'Module 1',
        'slug' => 'module-1',
        'content' => 'Content here',
        'order' => 1,
    ]);

    $progress = AffiliateTrainingProgress::create([
        'affiliate_id' => $affiliate->id,
        'module_id' => $module->id,
        'completed_at' => now(),
    ]);

    expect($progress)->toBeInstanceOf(AffiliateTrainingProgress::class);
    expect($progress->completed_at)->not->toBeNull();
});

test('AffiliateTrainingProgress has affiliate relationship', function (): void {
    $progress = new AffiliateTrainingProgress;

    expect($progress->affiliate())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateTrainingProgress has module relationship', function (): void {
    $progress = new AffiliateTrainingProgress;

    expect($progress->module())->toBeInstanceOf(BelongsTo::class);
});

// AffiliateCommissionPromotion Tests
test('AffiliateCommissionPromotion can be created', function (): void {
    $promo = AffiliateCommissionPromotion::create([
        'name' => 'Holiday Bonus',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
        'bonus_type' => 'percentage',
        'bonus_value' => 500,
        'is_active' => true,
    ]);

    expect($promo)->toBeInstanceOf(AffiliateCommissionPromotion::class);
    expect($promo->name)->toBe('Holiday Bonus');
});
