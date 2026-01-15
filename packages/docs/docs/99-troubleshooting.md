---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions for the Docs package.

## PDF Generation

### PDF Not Generating

**Symptom:** `generatePdf()` throws an error or returns empty content.

**Solutions:**

1. **Install Puppeteer**
   ```bash
   npm install puppeteer
   ```

2. **Check Node.js version**
   ```bash
   node --version  # Requires 18+
   ```

3. **Verify Chromium installation**
   Puppeteer should auto-download Chromium. If blocked by firewall:
   ```bash
   export PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
   # Then install Chromium manually
   ```

4. **Check view exists**
   ```php
   view()->exists('docs::templates.doc-default') // Should return true
   ```

### PDF Has Wrong Content

**Symptom:** PDF shows incorrect data or missing sections.

**Solutions:**

1. **Verify template is loaded**
   ```php
   $doc->template; // Should return DocTemplate model
   ```

2. **Check view name normalization**
   The package normalizes view names. All these are equivalent:
   - `modern`
   - `templates.modern`
   - `docs::templates.modern`

3. **Test view directly**
   ```php
   return view('docs::templates.doc-default', ['doc' => $doc]);
   ```

### Background Colors Not Printing

**Symptom:** PDFs show white background instead of styled colors.

**Solution:** Enable background printing in config or template:

```php
// config/docs.php
'pdf' => [
    'print_background' => true,
],

// Or per-template
DocTemplate::create([
    'settings' => [
        'pdf' => [
            'print_background' => true,
        ],
    ],
]);
```

---

## Document Creation

### "Invalid template selection" Error

**Symptom:** `ValidationException` when creating document with `doc_template_id`.

**Solutions:**

1. **Check template exists**
   ```php
   DocTemplate::find($templateId); // Should not be null
   ```

2. **Check owner scoping**
   If owner mode is enabled, template must belong to same owner:
   ```php
   DocTemplate::forOwner($owner)->find($templateId);
   ```

### Document Number Collision

**Symptom:** Unique constraint violation on `doc_number`.

**Solutions:**

1. **Check sequence configuration**
   ```php
   $sequence = DocSequence::where('doc_type', 'invoice')
       ->where('is_active', true)
       ->first();
   ```

2. **Use database transactions**
   The `SequenceManager` uses `lockForUpdate()`. Ensure you're not bypassing it.

3. **Custom number strategy**
   Implement `DocumentNumberStrategy` for custom logic:
   ```php
   class MyNumberStrategy implements DocumentNumberStrategy
   {
       public function generate(string $docType): string
       {
           // Your collision-resistant logic
       }
   }
   ```

---

## Multi-Tenancy

### Documents Showing Across Tenants

**Symptom:** Users see documents from other tenants.

**Solutions:**

1. **Verify owner mode is enabled**
   ```php
   config('docs.owner.enabled'); // Should be true
   ```

2. **Check OwnerResolver is bound**
   ```php
   use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
   
   app()->bound(OwnerResolverInterface::class); // Should be true
   ```

3. **Verify current owner**
   ```php
   use AIArmada\CommerceSupport\Support\OwnerContext;
   
   OwnerContext::resolve(); // Should return Model|null
   ```

### Global Templates Not Available

**Symptom:** Templates with `owner_type = null` not showing.

**Solution:** Enable `include_global`:

```php
// config/docs.php
'owner' => [
    'enabled' => true,
    'include_global' => true, // Add this
],
```

---

## Status Management

### Status Not Updating

**Symptom:** `markAsPaid()` or similar methods have no effect.

**Solutions:**

1. **Check current status**
   Some transitions are blocked:
   ```php
   $doc->cancel(); // Does nothing if already PAID
   ```

2. **Use service method for full tracking**
   ```php
   $docService->updateDocStatus($doc, DocStatus::PAID, 'Manual override');
   ```

### Status History Not Recording

**Symptom:** `statusHistories` relation is empty after status change.

**Solutions:**

1. **Use model methods** which auto-create history:
   ```php
   $doc->markAsPaid();  // Creates history entry
   ```

2. **Check owner columns** when owner mode is enabled, history needs owner data

---

## Email

### Emails Not Sending

**Symptom:** `DocEmailService::send()` doesn't deliver emails.

**Current Limitation:** The `queueEmail()` method is a stub. Implement actual mailing:

```php
// In your service provider or via event
Doc::observe(new class {
    public function updated(Doc $doc): void
    {
        if ($doc->wasChanged('status') && $doc->status === DocStatus::SENT) {
            // Dispatch your mailable
        }
    }
});
```

### Tracking Tokens Invalid

**Symptom:** `trackOpen()` returns false.

**Solutions:**

1. **Check encryption key consistency**
   Tokens use Laravel's `Crypt` facade. Key changes invalidate tokens.

2. **Check token format**
   Tokens should be URL-safe encrypted JSON.

---

## Configuration

### Config Changes Not Taking Effect

**Symptom:** Modified `config/docs.php` values not applied.

**Solutions:**

```bash
php artisan config:clear
php artisan cache:clear
```

### Table Names Wrong

**Symptom:** Queries fail with "table not found".

**Solution:** Verify migration prefix matches config:

```php
// config/docs.php
'database' => [
    'table_prefix' => 'docs_', // Must match migration
],
```

---

## Common Errors

### Class Not Found: DocData

**Error:** `Class AIArmada\Docs\Data\DocData not found`

**Solution:** Use correct namespace:
```php
use AIArmada\Docs\DataObjects\DocData; // Correct
// NOT: use AIArmada\Docs\Data\DocData;
```

### Migration Failed

**Error:** Migration fails on JSON columns.

**Solution:** Configure JSON column type for your database:

```php
// config/docs.php
'database' => [
    'json_column_type' => 'jsonb', // For PostgreSQL
    // or 'json' for MySQL
],
```

### Heroicon Not Found

**Error:** Heroicon class constant issues.

**Solution:** Ensure using Filament v5 compatible icon syntax. The package uses `Heroicon::OutlinedDocumentText` format.

---

## Getting Help

1. Check the [GitHub Issues](https://github.com/aiarmada/commerce/issues)
2. Review the vision docs in `docs/vision/` for upcoming features
3. Ensure you're using compatible versions (PHP 8.4+, Laravel 12+)
