---
title: Troubleshooting
---

# Troubleshooting

## Plugin Loads But Menu Items Missing

- Check `filament-signals.features.*` flags.
- Confirm the plugin is registered on the expected panel.

## Pages Error Due To Missing Data

- Ensure `aiarmada/signals` migrations are migrated.
- Confirm tracked properties/events exist for selected filters.

## Widgets Not Visible

- Verify `features.widgets`, `features.trend_chart`, and `features.pending_alerts_widget` are enabled.
- Verify current user has access to the dashboard page where widgets are mounted.

## Empty Reports

- Verify events are being ingested.
- Run metrics aggregation command from Signals package.
- Check date range and tracked property filters.
