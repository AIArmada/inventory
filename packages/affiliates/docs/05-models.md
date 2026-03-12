---
title: Models Reference
---

# Models Reference

The affiliates package includes 28 Eloquent models. This reference covers the primary models and their relationships.

## Core Models

### Affiliate

The main affiliate/partner model.

```php
use AIArmada\Affiliates\Models\Affiliate;
```

**Key Attributes:**

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | uuid | Primary key |
| `code` | string | Unique affiliate code |
| `name` | string | Affiliate name |
| `status` | AffiliateStatus | Current status |
| `commission_type` | CommissionType | Percentage or fixed |
| `commission_rate` | int | Rate in basis points or minor units |
| `currency` | string | ISO currency code |
| `parent_affiliate_id` | uuid | Parent for MLM networks |
| `default_voucher_code` | string | Default voucher for this affiliate |
| `contact_email` | string | Contact email |
| `owner_type` | string | Polymorphic owner type |
| `owner_id` | uuid | Polymorphic owner ID |
| `activated_at` | timestamp | When affiliate was activated |

**Relationships:**

```php
$affiliate->conversions;      // HasMany<AffiliateConversion>
$affiliate->attributions;     // HasMany<AffiliateAttribution>
$affiliate->payouts;          // HasMany<AffiliatePayout>
$affiliate->links;            // HasMany<AffiliateLink>
$affiliate->balance;          // HasOne<AffiliateBalance>
$affiliate->parent;           // BelongsTo<Affiliate>
$affiliate->children;         // HasMany<Affiliate>
$affiliate->programs;         // BelongsToMany<AffiliateProgram>
$affiliate->fraudSignals;     // HasMany<AffiliateFraudSignal>
$affiliate->dailyStats;       // HasMany<AffiliateDailyStat>
$affiliate->commissionRules;  // HasMany<AffiliateCommissionRule>
$affiliate->volumeTiers;      // HasMany<AffiliateVolumeTier>
```

**Key Methods:**

```php
$affiliate->isActive();           // Check if status is Active
$affiliate->getCommissionRate();  // Get rate as decimal
$affiliate->getTotalEarnings();   // Sum of approved commissions
$affiliate->getPendingEarnings(); // Sum of pending commissions
```

### AffiliateAttribution

Tracks when a visitor is attributed to an affiliate.

```php
use AIArmada\Affiliates\Models\AffiliateAttribution;
```

**Key Attributes:**

| Attribute | Type | Description |
|-----------|------|-------------|
| `affiliate_id` | uuid | The credited affiliate |
| `affiliate_code` | string | Code used at time of attribution |
| `subject_type` | string | Neutral subject type (`product`, `order`, etc.) |
| `subject_identifier` | string | Neutral subject identifier |
| `subject_instance` | string | Neutral subject instance/context |
| `subject_title_snapshot` | string | Snapshot title for subject at attribution time |
| `cart_identifier` | string | Cart session identifier |
| `cart_instance` | string | Cart instance name |
| `cookie_value` | string | Tracking cookie value |
| `voucher_code` | string | Voucher code if used |
| `landing_url` | string | First page visited |
| `referrer_url` | string | Referring URL |
| `source` | string | UTM source |
| `medium` | string | UTM medium |
| `campaign` | string | UTM campaign |
| `user_agent` | string | Browser user agent |
| `ip_address` | string | Visitor IP |
| `expires_at` | timestamp | Attribution expiry |

**Relationships:**

```php
$attribution->affiliate;    // BelongsTo<Affiliate>
$attribution->conversions;  // HasMany<AffiliateConversion>
$attribution->touchpoints;  // HasMany<AffiliateTouchpoint>
```

Compatibility aliases are maintained for legacy cart semantics:

- `subject_identifier` <-> `cart_identifier`
- `subject_instance` <-> `cart_instance`

### AffiliateConversion

Records a successful conversion (sale, signup, etc.).

```php
use AIArmada\Affiliates\Models\AffiliateConversion;
```

**Key Attributes:**

| Attribute | Type | Description |
|-----------|------|-------------|
| `affiliate_id` | uuid | The credited affiliate |
| `affiliate_attribution_id` | uuid | Source attribution |
| `affiliate_payout_id` | uuid | Payout batch (if paid) |
| `subject_type` | string | Neutral subject type |
| `subject_identifier` | string | Neutral subject identifier |
| `subject_instance` | string | Neutral subject instance/context |
| `subject_title_snapshot` | string | Snapshot title for subject |
| `external_reference` | string | Neutral external reference |
| `order_reference` | string | External order ID |
| `conversion_type` | string | Conversion category (`purchase`, etc.) |
| `subtotal_minor` | int | Order subtotal in minor units |
| `value_minor` | int | Neutral conversion value in minor units |
| `total_minor` | int | Order total in minor units |
| `commission_minor` | int | Commission amount in minor units |
| `commission_currency` | string | Commission currency |
| `status` | ConversionStatus | Pending, Approved, Rejected, Paid |
| `occurred_at` | timestamp | When conversion occurred |
| `approved_at` | timestamp | When approved |

**Relationships:**

```php
$conversion->affiliate;    // BelongsTo<Affiliate>
$conversion->attribution;  // BelongsTo<AffiliateAttribution>
$conversion->payout;       // BelongsTo<AffiliatePayout>
```

Compatibility aliases are provided:

- `external_reference` <-> `order_reference`
- `value_minor` <-> `total_minor`
- `subject_identifier` <-> `cart_identifier`
- `subject_instance` <-> `cart_instance`

### AffiliatePayout

Batch payout record for commissions.

```php
use AIArmada\Affiliates\Models\AffiliatePayout;
```

**Key Attributes:**

| Attribute | Type | Description |
|-----------|------|-------------|
| `reference` | string | Unique payout reference |
| `status` | PayoutStatus | Pending, Processing, Completed, Failed |
| `total_minor` | int | Total payout amount |
| `currency` | string | Payout currency |
| `payee_type` | string | Polymorphic payee type |
| `payee_id` | uuid | Polymorphic payee ID |
| `scheduled_at` | timestamp | When payout is scheduled |
| `paid_at` | timestamp | When payment was made |
| `metadata` | array | Additional data (bank details, notes) |

**Relationships:**

```php
$payout->conversions;  // HasMany<AffiliateConversion>
$payout->events;       // HasMany<AffiliatePayoutEvent>
$payout->payee;        // MorphTo (Affiliate or custom)
```

## Program Models

### AffiliateProgram

Affiliate program definition with commission rules.

```php
use AIArmada\Affiliates\Models\AffiliateProgram;
```

**Key Attributes:**

| Attribute | Type | Description |
|-----------|------|-------------|
| `name` | string | Program name |
| `slug` | string | URL-friendly slug |
| `status` | ProgramStatus | Draft, Active, Paused, Ended |
| `requires_approval` | bool | Manual approval required |
| `is_public` | bool | Publicly visible |
| `default_commission_rate_basis_points` | int | Default commission |
| `commission_type` | string | Percentage or fixed |
| `cookie_lifetime_days` | int | Attribution window |
| `eligibility_rules` | array | Program requirements |
| `starts_at` | timestamp | Program start date |
| `ends_at` | timestamp | Program end date |

**Relationships:**

```php
$program->tiers;        // HasMany<AffiliateProgramTier>
$program->memberships;  // HasMany<AffiliateProgramMembership>
$program->creatives;    // HasMany<AffiliateProgramCreative>
$program->affiliates;   // BelongsToMany<Affiliate>
```

### AffiliateProgramMembership

Affiliate enrollment in a program.

```php
$membership->affiliate;  // BelongsTo<Affiliate>
$membership->program;    // BelongsTo<AffiliateProgram>
$membership->tier;       // BelongsTo<AffiliateProgramTier>
```

## Balance & Financial Models

### AffiliateBalance

Real-time balance tracking.

```php
use AIArmada\Affiliates\Models\AffiliateBalance;
```

| Attribute | Type | Description |
|-----------|------|-------------|
| `affiliate_id` | uuid | The affiliate |
| `available_minor` | int | Available for withdrawal |
| `pending_minor` | int | Pending approval |
| `holding_minor` | int | On hold (maturity, fraud review) |
| `lifetime_minor` | int | Total earned all-time |
| `currency` | string | Balance currency |

### AffiliatePayoutMethod

Stored payout methods.

```php
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
```

| Attribute | Type | Description |
|-----------|------|-------------|
| `type` | PayoutMethodType | PayPal, Stripe, BankTransfer |
| `is_default` | bool | Primary method |
| `is_verified` | bool | Verified by system |
| `details` | array | Encrypted payout details |

### AffiliatePayoutHold

Temporary holds on payouts.

```php
use AIArmada\Affiliates\Models\AffiliatePayoutHold;
```

| Attribute | Type | Description |
|-----------|------|-------------|
| `reason` | string | Hold reason |
| `amount_minor` | int | Amount on hold |
| `released_at` | timestamp | When released |

## Fraud & Analytics Models

### AffiliateFraudSignal

Detected fraud signals.

```php
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
```

| Attribute | Type | Description |
|-----------|------|-------------|
| `affiliate_id` | uuid | The affiliate |
| `signal_type` | string | Type of fraud signal |
| `severity` | FraudSeverity | Low, Medium, High, Critical |
| `status` | FraudSignalStatus | Pending, Reviewed, Dismissed |
| `score` | int | Fraud score (0-100) |
| `details` | array | Signal details |

### AffiliateDailyStat

Aggregated daily statistics.

```php
use AIArmada\Affiliates\Models\AffiliateDailyStat;
```

| Attribute | Type | Description |
|-----------|------|-------------|
| `affiliate_id` | uuid | The affiliate |
| `date` | date | Statistics date |
| `clicks` | int | Click count |
| `conversions` | int | Conversion count |
| `revenue_minor` | int | Revenue generated |
| `commission_minor` | int | Commission earned |

## Network Models

### AffiliateNetwork

Parent-child relationships for MLM structures.

### AffiliateRank

Rank definitions (Bronze, Silver, Gold, etc.).

### AffiliateRankHistory

Rank change history for affiliates.

## Training & Support Models

### AffiliateTrainingModule

Training content for affiliates.

### AffiliateTrainingProgress

Affiliate progress through training.

### AffiliateSupportTicket / AffiliateSupportMessage

Support ticket system for affiliate queries.

### AffiliateTaxDocument

Tax document storage (W-9, 1099).
