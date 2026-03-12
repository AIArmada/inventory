---
title: Usage
---

# Usage

## Registered Pages

When enabled, the plugin registers:

- `SignalsDashboard`
- `PageViewsReport`
- `ConversionFunnelReport`
- `AcquisitionReport`
- `JourneyReport`
- `RetentionReport`
- `ContentPerformanceReport`
- `LiveActivityReport`
- `GoalsReport`

## Registered Resources

When enabled, the plugin registers:

- `TrackedPropertyResource`
- `SignalGoalResource`
- `SignalSegmentResource`
- `SavedSignalReportResource`
- `SignalAlertRuleResource`
- `SignalAlertLogResource`

## Dashboard Widgets

The package ships with widgets used by dashboard/report pages:

- `SignalsStatsWidget`
- `EventTrendWidget`
- `PendingSignalAlertsWidget`

## Saved Reports Workflow

1. Build report filters on report pages.
2. Save definition via `SavedSignalReportResource`.
3. Re-open saved report from report-page actions.

This is particularly useful for funnel/journey presets and team-shared analytics views.
