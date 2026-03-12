---
title: Installation
---

# Installation

## 1. Install Package

```bash
composer require aiarmada/filament-signals
```

## 2. Publish Config (Optional)

```bash
php artisan vendor:publish --tag=filament-signals-config
```

## 3. Register Plugin In Filament Panel

```php
use AIArmada\FilamentSignals\FilamentSignalsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        FilamentSignalsPlugin::make(),
    ]);
}
```

## 4. Ensure Signals Package Is Installed

The plugin depends on Signals models/services and underlying signals tables.
