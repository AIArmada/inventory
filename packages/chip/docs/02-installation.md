---
title: Installation
---

# Installation

## Requirements

- PHP 8.4 or higher
- Laravel 12.x
- A CHIP merchant account

## Install via Composer

```bash
composer require aiarmada/chip
```

The package auto-registers its service provider via Laravel's package discovery.

## Publish Configuration

```bash
php artisan vendor:publish --tag=chip-config
```

This creates `config/chip.php` with all available options.

## Run Migrations

```bash
php artisan migrate
```

The package creates the following tables (with configurable prefix):
- `chip_purchases` - Payment records from CHIP
- `chip_payments` - Payment details (fees, net amounts)
- `chip_webhooks` - Webhook event log
- `chip_bank_accounts` - Saved bank accounts (Send)
- `chip_clients` - Customer records
- `chip_send_instructions` - Payout records
- `chip_send_limits` - Payout limits
- `chip_send_webhooks` - Send webhook records
- `chip_company_statements` - Settlement statements
- `chip_daily_metrics` - Pre-aggregated analytics

## Environment Variables

Add these to your `.env` file:

```env
# Environment: sandbox or production
CHIP_ENVIRONMENT=sandbox

# CHIP Collect (Payments)
CHIP_COLLECT_API_KEY=your_collect_api_key
CHIP_COLLECT_BRAND_ID=your_brand_uuid

# CHIP Send (Payouts) - Optional
CHIP_SEND_API_KEY=your_send_api_key
CHIP_SEND_API_SECRET=your_send_api_secret

# Webhook Security
CHIP_COMPANY_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"

# Redirects
CHIP_SUCCESS_REDIRECT=https://your-app.com/payment/success
CHIP_FAILURE_REDIRECT=https://your-app.com/payment/failed

# Optional Settings
CHIP_DEFAULT_CURRENCY=MYR
CHIP_SEND_RECEIPT=false
CHIP_WEBHOOK_ROUTE=/chip/webhook
```

## Obtaining API Credentials

### CHIP Collect
1. Log in to your [CHIP Dashboard](https://dashboard.chip-in.asia)
2. Navigate to **Settings > API Keys**
3. Create or copy your **Secret Key** for `CHIP_COLLECT_API_KEY`
4. Copy your **Brand ID** from **Settings > Brands**

### CHIP Send
1. In the CHIP Dashboard, go to **Send > Settings**
2. Generate API Key and Secret
3. Note: Send API uses different credentials than Collect

### Company Public Key
1. Go to **Settings > API Keys**
2. Copy the **Public Key** for webhook signature verification
3. This is essential for production security

## Webhook Setup

### 1. Configure in CHIP Dashboard

Add your webhook URL in the CHIP Dashboard:
```
https://your-app.com/chip/webhook
```

### 2. Ensure Route is Accessible

The package automatically registers the webhook route. Make sure:
- The route is excluded from CSRF verification
- Your firewall allows POST requests from CHIP servers

Add to `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'chip/webhook',
];
```

### 3. Queue Configuration (Recommended)

For production, configure a queue driver to process webhooks asynchronously:

```env
QUEUE_CONNECTION=redis
```

## Verifying Installation

Run the health check command:

```bash
php artisan chip:health-check
```

This verifies:
- API connectivity
- Credential validity
- Webhook configuration
- Database tables

## Next Steps

- [Configuration](03-configuration.md) - Customize package behavior
- [Usage Guide](04-usage.md) - Create your first payment
- [Webhooks](webhooks.md) - Handle payment events
