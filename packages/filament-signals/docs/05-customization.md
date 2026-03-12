---
title: Customization
---

# Customization

## Feature-Gated Rollout

Use `filament-signals.features.*` flags to progressively enable analytics surfaces by environment or tenant.

## Navigation Order

Use `filament-signals.resources.navigation_sort.*` to align menu order with your panel IA.

## Label Overrides

Use:

- `filament-signals.resources.labels.outcomes`
- `filament-signals.resources.labels.monetary_value`

These labels are consumed by UI helpers and widgets.

## Extending Plugin Registration

If you need additional app-specific pages/resources, register them in your panel provider alongside `FilamentSignalsPlugin::make()`.
