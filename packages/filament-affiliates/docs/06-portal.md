---
title: Portal
---

# Affiliate Portal

The plugin includes a complete self-service portal for affiliates.

## Overview

The portal allows affiliates to:

- View dashboard with performance metrics
- Generate tracking links
- Monitor conversions
- Track payouts
- Update profile settings

## Enabling the Portal

The portal is enabled by default. Configure in `config/filament-affiliates.php`:

```php
'portal' => [
    'panel_id' => 'affiliate',
    'path' => 'affiliate',
    'brand_name' => 'Affiliate Portal',
    'primary_color' => '#6366f1',
],
```

## Portal Pages

### Dashboard

The main portal dashboard displays:

- Lifetime earnings summary
- Monthly performance chart
- Recent conversions list
- Pending payout amount
- Quick link generator

```php
namespace AIArmada\FilamentAffiliates\Pages\Portal;

class Dashboard extends Page
{
    protected static string $view = 'filament-affiliates::pages.portal.dashboard';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Earnings', $this->affiliate->totalEarnings()),
            Stat::make('This Month', $this->affiliate->monthlyEarnings()),
            Stat::make('Pending', $this->affiliate->pendingCommission()),
            Stat::make('Conversions', $this->affiliate->conversions()->count()),
        ];
    }
}
```

### Link Generator

Create and manage tracking links:

- Generate links for any URL
- Copy to clipboard
- QR code generation
- Link analytics (clicks, conversions)

```php
class LinksPage extends Page
{
    public function generateLink(): void
    {
        $link = $this->affiliateLinkService->generate(
            affiliate: $this->affiliate,
            targetUrl: $this->url,
            campaign: $this->campaign,
        );

        $this->createdLink = $link->full_url;
    }
}
```

### Conversions

View conversion history with:

- Date/time of conversion
- Reference (`external_reference`)
- Total value (`value_minor`)
- Commission amount
- Status (pending, approved, paid)
- Filter by date range and status

### Payouts

Track payout history:

- Payout date
- Amount
- Payment method
- Status
- Transaction reference

### Registration

Self-registration for new affiliates:

```php
class RegistrationPage extends Page
{
    public function register(): void
    {
        $affiliate = $this->affiliateRegistrationService->register(
            user: auth()->user(),
            programId: $this->program_id,
            referralCode: $this->referral_code,
        );

        $this->redirect(route('filament.affiliate.pages.dashboard'));
    }
}
```

## Authentication

### Using Existing Auth

The portal uses Laravel's authentication:

```php
'portal' => [
    'auth_guard' => 'web',
    'login_enabled' => true,
],
```

### Custom Login Page

Customize the login page:

```php
namespace App\Filament\Affiliate\Pages;

use AIArmada\FilamentAffiliates\Pages\Portal\Login as BaseLogin;

class Login extends BaseLogin
{
    protected function getFormSchema(): array
    {
        return [
            ...parent::getFormSchema(),
            Forms\Components\Checkbox::make('remember')
                ->label('Remember me'),
        ];
    }
}
```

## Portal Authorization

Ensure only affiliates access the portal:

```php
// AffiliatePortalProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('affiliate')
        ->path('affiliate')
        ->authMiddleware([
            EnsureUserIsAffiliate::class,
        ]);
}
```

```php
// EnsureUserIsAffiliate.php
class EnsureUserIsAffiliate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->affiliate) {
            return redirect()->route('filament.affiliate.pages.registration');
        }

        if ($user->affiliate->status !== AffiliateStatus::Active) {
            abort(403, 'Your affiliate account is not active.');
        }

        return $next($request);
    }
}
```

## Customizing Portal Pages

### Override Dashboard

```php
namespace App\Filament\Affiliate\Pages;

use AIArmada\FilamentAffiliates\Pages\Portal\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'affiliate.pages.dashboard';

    protected function getViewData(): array
    {
        return [
            ...parent::getViewData(),
            'announcements' => Announcement::latest()->limit(5)->get(),
        ];
    }
}
```

### Add Custom Pages

```php
namespace App\Filament\Affiliate\Pages;

use Filament\Pages\Page;

class Resources extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document';

    protected static ?string $navigationLabel = 'Resources';

    protected static string $view = 'affiliate.pages.resources';
}
```

## Portal Theming

### Brand Colors

```php
'portal' => [
    'primary_color' => '#your-brand-color',
],
```

### Custom Logo

In your panel provider:

```php
$panel
    ->brandLogo(asset('images/affiliate-logo.svg'))
    ->darkModeBrandLogo(asset('images/affiliate-logo-dark.svg'))
    ->favicon(asset('images/favicon.ico'));
```

### Custom CSS

Publish views and add custom styles:

```bash
php artisan vendor:publish --tag=filament-affiliates-views
```

## Disabling Portal Features

Disable specific features:

```php
'portal' => [
    'features' => [
        'dashboard' => true,
        'links' => true,
        'conversions' => true,
        'payouts' => false,  // Hide payout history
    ],
],
```

When `affiliates.features.commission_tracking.enabled` is false, portal payouts are automatically disabled regardless of portal feature config.

## Portal Webhooks

Notify affiliates of events:

```php
// Listen for portal-relevant events
Event::listen(ConversionApproved::class, function ($event) {
    $event->conversion->affiliate->notify(
        new ConversionApprovedNotification($event->conversion)
    );
});
```

## Portal Security

### Rate Limiting

Add rate limiting to portal routes:

```php
Route::middleware(['throttle:affiliate-portal'])->group(function () {
    // Portal routes
});
```

### Session Management

```php
'portal' => [
    'session_lifetime' => 120, // minutes
    'single_session' => false,
],
```
