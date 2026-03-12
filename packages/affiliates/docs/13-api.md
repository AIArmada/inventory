---
title: Public API
---

# Public API

## Enable API

```php
'api' => [
    'enabled' => env('AFFILIATES_API_ENABLED', false),
    'prefix' => env('AFFILIATES_API_PREFIX', 'api/affiliates'),
    'middleware' => ['api', 'throttle:60,1'],
    'auth' => env('AFFILIATES_API_AUTH', 'token'),
    'token' => env('AFFILIATES_API_TOKEN'),
],
```

## Endpoints

- `GET /api/affiliates/{code}/summary`
- `POST /api/affiliates/{code}/links`
- `GET /api/affiliates/{code}/creatives`

## Link Endpoint

`POST /api/affiliates/{code}/links` accepts subject-aware metadata:

```json
{
  "url": "https://example.com/products/sku-1001",
  "ttl": 3600,
  "params": {"utm_campaign": "spring-launch"},
  "subject_type": "product",
  "subject_identifier": "SKU-1001",
  "subject_instance": "web",
  "subject_title_snapshot": "Pro Plan",
  "subject_metadata": {"category": "subscriptions"}
}
```

Success response:

```json
{
  "id": "uuid",
  "link": "https://example.com/products/sku-1001?aff=PARTNER42...",
  "subject_type": "product",
  "subject_identifier": "SKU-1001"
}
```

## Owner Context

If owner scoping is enabled and global rows are disabled, API requests require resolved owner context; otherwise, endpoints return `400` with `Owner context required`.
