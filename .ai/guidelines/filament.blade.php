# Filament Guidelines

## Spatie Integrations (Mandatory)

When implementing Filament functionality around Spatie packages, you MUST use the official FilamentPHP plugins (do not roll your own integrations or use third-party alternatives):

- Tags (Spatie Laravel Tags): https://github.com/filamentphp/spatie-laravel-tags-plugin
- Settings (Spatie Laravel Settings): https://github.com/filamentphp/spatie-laravel-settings-plugin
- Google Fonts (Spatie Laravel Google Fonts): https://github.com/filamentphp/spatie-laravel-google-fonts-plugin
- Media Library (Spatie Laravel Media Library): https://github.com/filamentphp/spatie-laravel-media-library-plugin

## Import / Export (Mandatory)

For any import or export workflows in Filament, you MUST use Filament's built-in Actions:

- Import: https://filamentphp.com/docs/4.x/actions/import
- Export: https://filamentphp.com/docs/4.x/actions/export

## Rules

- Do not introduce alternative import/export libraries (e.g., custom CSV/XLSX handlers) unless explicitly requested and approved.
- Prefer official Filament plugins and documented APIs over custom panels, fields, or bespoke integrations.
- If a feature is covered by an official plugin/action, use it as the default implementation path.
