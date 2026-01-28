# Customers Package: Spatie Integration Blueprint

> **Package:** `aiarmada/customers`  
> **Status:** Planned (Vision Only)  
> **Role:** Core Layer - Customer Relationship Management

---

## 📋 Current Vision Analysis

Based on ecosystem architecture documentation, the customers package is designed to provide:

- Customer profiles & authentication
- Address management
- Customer groups/segments
- Purchase history aggregation
- Wishlist functionality
- Customer analytics

---

## 🎯 Critical Integration: laravel-activitylog

### Customer Activity Tracking

Every customer interaction should be logged for:
- CRM analytics
- Customer service support
- Fraud detection
- Marketing insights

```php
// customers/src/Models/Customer.php

namespace AIArmada\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class Customer extends Model
{
    use HasUuids;
    use LogsCommerceActivity;

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'phone',
        'date_of_birth',
        'marketing_consent',
        'metadata',
    ];

    protected function getLoggableAttributes(): array
    {
        return [
            'email',
            'first_name',
            'last_name',
            'phone',
            'marketing_consent',
        ];
    }

    protected function getActivityLogName(): string
    {
        return 'customers';
    }

    // Explicit activity logging for important events
    public function recordLogin(string $ip, string $userAgent): void
    {
        activity('customer-logins')
            ->performedOn($this)
            ->withProperties([
                'ip' => $ip,
                'user_agent' => $userAgent,
                'timestamp' => now()->toIso8601String(),
            ])
            ->log('Customer logged in');
    }

    public function recordPasswordChange(): void
    {
        activity('customers')
            ->performedOn($this)
            ->log('Customer changed password');
    }

    public function recordEmailVerification(): void
    {
        activity('customers')
            ->performedOn($this)
            ->log('Customer verified email address');
    }
}
```

### Address Activity Logging

```php
// customers/src/Models/Address.php

namespace AIArmada\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class Address extends Model
{
    use LogsCommerceActivity;

    protected $fillable = [
        'customer_id',
        'label',
        'first_name',
        'last_name',
        'company',
        'line1',
        'line2',
        'city',
        'state',
        'postcode',
        'country',
        'phone',
        'is_default_billing',
        'is_default_shipping',
    ];

    protected function getLoggableAttributes(): array
    {
        return [
            'label',
            'city',
            'state',
            'country',
            'is_default_billing',
            'is_default_shipping',
        ];
    }
}
```

---

## 🎯 Secondary Integration: laravel-tags

### Customer Segments & Tags

Tags enable powerful customer segmentation without complex database relationships.

```php
// customers/src/Models/Customer.php

use Spatie\Tags\HasTags;

class Customer extends Model
{
    use HasTags;

    // Tag types for customers
    public const TAG_TYPE_SEGMENT = 'segment';
    public const TAG_TYPE_BEHAVIOR = 'behavior';
    public const TAG_TYPE_MARKETING = 'marketing';
    public const TAG_TYPE_INTERNAL = 'internal';

    /**
     * Attach customer segment tags
     */
    public function addToSegment(string ...$segments): self
    {
        $this->attachTags($segments, self::TAG_TYPE_SEGMENT);
        return $this;
    }

    public function removeFromSegment(string ...$segments): self
    {
        $this->detachTags($segments, self::TAG_TYPE_SEGMENT);
        return $this;
    }

    /**
     * Get customers in specific segment
     */
    public function scopeInSegment($query, string ...$segments)
    {
        return $query->withAnyTags($segments, self::TAG_TYPE_SEGMENT);
    }

    /**
     * Auto-segment based on behavior
     */
    public function updateBehaviorTags(): void
    {
        $behaviors = [];

        // High-value customer
        if ($this->lifetime_value > 10000) {
            $behaviors[] = 'vip';
        } elseif ($this->lifetime_value > 5000) {
            $behaviors[] = 'high-value';
        }

        // Frequent buyer
        if ($this->orders_count > 10) {
            $behaviors[] = 'frequent-buyer';
        }

        // Recent customer
        if ($this->last_order_at > now()->subDays(30)) {
            $behaviors[] = 'active';
        } elseif ($this->last_order_at < now()->subMonths(6)) {
            $behaviors[] = 'at-risk';
        } elseif ($this->last_order_at < now()->subYear()) {
            $behaviors[] = 'churned';
        }

        $this->syncTagsWithType($behaviors, self::TAG_TYPE_BEHAVIOR);
    }

    /**
     * Marketing preference tags
     */
    public function syncMarketingTags(array $preferences): void
    {
        $tags = [];

        if ($preferences['newsletter'] ?? false) {
            $tags[] = 'newsletter';
        }
        if ($preferences['sms'] ?? false) {
            $tags[] = 'sms-marketing';
        }
        if ($preferences['promotions'] ?? false) {
            $tags[] = 'promotions';
        }

        $this->syncTagsWithType($tags, self::TAG_TYPE_MARKETING);
    }
}
```

### Tag-Based Queries

```php
// Query examples for marketing campaigns

// All VIP customers who accept promotions
Customer::inSegment('vip')
    ->withAnyTags(['promotions'], Customer::TAG_TYPE_MARKETING)
    ->get();

// At-risk customers for win-back campaign
Customer::withAllTags(['at-risk'], Customer::TAG_TYPE_BEHAVIOR)
    ->where('marketing_consent', true)
    ->get();

// New customers in specific region
Customer::withAnyTags(['new-customer'], Customer::TAG_TYPE_SEGMENT)
    ->whereHas('addresses', fn ($q) => $q->where('country', 'MY'))
    ->get();
```

---

## 🎯 Tertiary Integration: laravel-medialibrary

### Customer Avatars & Documents

```php
// customers/src/Models/Customer.php

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Customer extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        // Avatar (single file)
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useFallbackUrl('/images/default-avatar.png');

        // ID documents for verification
        $this->addMediaCollection('documents')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'application/pdf'])
            ->useDisk('private'); // Private disk for sensitive docs
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->sharpen(10)
            ->performOnCollections('avatar');

        $this->addMediaConversion('profile')
            ->width(300)
            ->height(300)
            ->performOnCollections('avatar');
    }

    public function getAvatarUrl(string $conversion = 'profile'): string
    {
        return $this->getFirstMediaUrl('avatar', $conversion) 
            ?: $this->getMediaCollections()['avatar']->fallbackUrl;
    }
}
```

---

## 🎯 Optional Integration: laravel-translatable

### Multi-Language Customer Communications

For international commerce, customer-facing content should be translatable.

```php
// customers/src/Models/CustomerSegment.php

namespace AIArmada\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class CustomerSegment extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'slug',
        'name',
        'description',
        'criteria',
        'is_dynamic',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'is_dynamic' => 'boolean',
        ];
    }
}
```

---

## 📊 Customer Data Flow

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                         CUSTOMER DATA ARCHITECTURE                            │
├──────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│   ┌────────────────────────────────────────────────────────────────────┐     │
│   │                        CUSTOMER                                     │     │
│   │  - Profile (name, email, phone)                                    │     │
│   │  - Authentication                                                   │     │
│   │  - Preferences                                                      │     │
│   └─────────────────────────┬──────────────────────────────────────────┘     │
│                             │                                                 │
│         ┌───────────────────┼───────────────────┬──────────────────┐         │
│         │                   │                   │                   │         │
│         ▼                   ▼                   ▼                   ▼         │
│   ┌───────────┐     ┌───────────────┐    ┌──────────┐       ┌──────────┐    │
│   │ Addresses │     │  Tags/Segments │    │  Media   │       │ Activity │    │
│   │           │     │               │    │          │       │   Log    │    │
│   │ - Billing │     │ - VIP         │    │ - Avatar │       │          │    │
│   │ - Shipping│     │ - Active      │    │ - Docs   │       │ - Logins │    │
│   │ - Multiple│     │ - Newsletter  │    │          │       │ - Orders │    │
│   └───────────┘     └───────────────┘    └──────────┘       │ - Updates│    │
│                             │                               └──────────┘    │
│                             │                                                │
│                     ┌───────┴───────┐                                       │
│                     │               │                                       │
│                     ▼               ▼                                       │
│              ┌───────────┐   ┌───────────────┐                              │
│              │  Segment  │   │   Behavior    │                              │
│              │   Tags    │   │     Tags      │                              │
│              │           │   │               │                              │
│              │ Manual    │   │ Auto-computed │                              │
│              │ assignment│   │ from orders   │                              │
│              └───────────┘   └───────────────┘                              │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 🎯 Customer Analytics with Activity Log

### Customer Timeline Query

```php
// customers/src/Services/CustomerTimeline.php

namespace AIArmada\Customers\Services;

use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use AIArmada\Customers\Models\Customer;

class CustomerTimeline
{
    public function getTimeline(Customer $customer, int $limit = 50): Collection
    {
        return Activity::query()
            ->forSubject($customer)
            ->orWhere(function ($query) use ($customer) {
                // Include activities on related models
                $query->where('properties->customer_id', $customer->id);
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity) => [
                'timestamp' => $activity->created_at,
                'type' => $activity->log_name,
                'description' => $activity->description,
                'properties' => $activity->properties,
                'causer' => $activity->causer?->name ?? 'System',
            ]);
    }

    public function getRecentActions(Customer $customer): array
    {
        $activities = Activity::forSubject($customer)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        return [
            'total_actions' => $activities->count(),
            'logins' => $activities->where('log_name', 'customer-logins')->count(),
            'profile_updates' => $activities->where('log_name', 'customers')
                ->where('description', 'like', '%updated%')
                ->count(),
            'last_activity' => $activities->max('created_at'),
        ];
    }
}
```

---

## 📦 composer.json Blueprint

### customers/composer.json

```json
{
    "name": "aiarmada/customers",
    "description": "Customer relationship management for AIArmada Commerce",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "aiarmada/commerce-support": "^1.0",
        "spatie/laravel-tags": "^4.6",
        "spatie/laravel-medialibrary": "^11.0"
    },
    "suggest": {
        "spatie/laravel-translatable": "For multi-language customer segments"
    },
    "autoload": {
        "psr-4": {
            "AIArmada\\Customers\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "AIArmada\\Customers\\CustomersServiceProvider"
            ]
        }
    }
}
```

---

## 📂 Proposed Package Structure

```
customers/
├── composer.json
├── config/
│   └── customers.php
├── database/
│   └── migrations/
│       ├── create_customers_table.php
│       ├── create_customer_addresses_table.php
│       └── create_customer_segments_table.php
├── docs/
│   ├── 01-overview.md
│   ├── 02-installation.md
│   ├── 03-configuration.md
│   ├── 04-models.md
│   ├── 05-segmentation.md
│   └── 06-activity-tracking.md
├── resources/
│   └── views/
│       └── emails/
├── routes/
│   └── api.php
└── src/
    ├── Commands/
    │   └── UpdateCustomerSegmentsCommand.php
    ├── Concerns/
    │   └── HasCustomerRelation.php
    ├── Contracts/
    │   └── CustomerRepository.php
    ├── Events/
    │   ├── CustomerCreated.php
    │   ├── CustomerUpdated.php
    │   └── CustomerSegmentChanged.php
    ├── Models/
    │   ├── Customer.php
    │   ├── Address.php
    │   └── CustomerSegment.php
    ├── Observers/
    │   └── CustomerObserver.php
    ├── Repositories/
    │   └── EloquentCustomerRepository.php
    ├── Services/
    │   ├── CustomerService.php
    │   ├── CustomerTimeline.php
    │   └── SegmentationService.php
    └── CustomersServiceProvider.php
```

---

## ✅ Implementation Checklist

### Phase 1: Core Models with Activity Logging

- [ ] Create Customer model with LogsCommerceActivity
- [ ] Create Address model with LogsCommerceActivity
- [ ] Add explicit logging methods (login, password change, etc.)
- [ ] Create CustomerTimeline service
- [ ] Write tests for activity logging

### Phase 2: Customer Segmentation with Tags

- [ ] Add HasTags to Customer model
- [ ] Define tag types (segment, behavior, marketing, internal)
- [ ] Create SegmentationService
- [ ] Create auto-segmentation command
- [ ] Build segment-based query scopes

### Phase 3: Media Integration

- [ ] Add HasMedia to Customer model
- [ ] Configure avatar collection
- [ ] Configure documents collection (private)
- [ ] Set up media conversions
- [ ] Add fallback avatar

### Phase 4: Translatable Segments

- [ ] Create CustomerSegment model with HasTranslations
- [ ] Add translatable fields (name, description)
- [ ] Create segment management interface

---

## 🔗 Related Documents

- [00-overview.md](00-overview.md) - Master overview
- [01-commerce-support.md](01-commerce-support.md) - Activity log foundation
- [02-products-package.md](02-products-package.md) - Similar media integration

---

*This blueprint was created by the Visionary Chief Architect.*
