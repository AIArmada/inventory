# Documentation Guidelines (Filament-Style)

Documentation follows Filament's structure: markdown files with Astro component imports stored in the main repo, consumed by a separate docs site.

## How Filament Does It

1. **Markdown in main repo** - `docs/` and `packages/*/docs/` contain plain markdown
2. **Astro imports in markdown** - Files include `import Aside from "@components/Aside.astro"` 
3. **Separate docs site** - A separate repository/project builds the actual website
4. **Docs site pulls markdown** - The Astro site copies/imports markdown from the main repo

## File Structure

### Naming Convention
```
packages/<package>/docs/
├── 01-overview.md           # Package introduction
├── 02-installation.md       # Setup instructions
├── 03-configuration.md      # Config options
├── 04-usage.md              # Basic usage
├── 05-<feature>.md          # Feature-specific docs
├── ...
└── 99-troubleshooting.md    # Common issues
```

- Use numbered prefixes (`01-`, `02-`) for ordering
- Use lowercase kebab-case for filenames
- One topic per file, max 500 lines

### Frontmatter (Required)
Every markdown file must have YAML frontmatter:

```yaml
---
title: Getting Started
---
```

Optional frontmatter fields:
```yaml
---
title: Overview
contents: false           # Hide table of contents
---
```

## Astro Components (For Future Docs Site)

Prepare markdown with Astro component imports that will work when the docs site is built:

```md
---
title: Configuration
---
import Aside from "@components/Aside.astro"
import AutoScreenshot from "@components/AutoScreenshot.astro"

## Introduction

<Aside variant="info">
    This feature requires PHP 8.4 or higher.
</Aside>

<Aside variant="warning">
    Breaking change in v2.0: The `oldMethod()` has been renamed to `newMethod()`.
</Aside>
```

### Available Components

| Component | Purpose | Variants |
|-----------|---------|----------|
| `<Aside>` | Callouts/alerts | `info`, `warning`, `tip`, `danger` |
| `<AutoScreenshot>` | Versioned screenshots | `version="1.x"` |
| `<Disclosure>` | Collapsible sections | - |

## Content Style

### Code Examples
Always include working, copy-paste ready examples:

```php
use AIArmada\Cart\Facades\Cart;

Cart::session('user-123')
    ->add([
        'id' => 'product-1',
        'name' => 'Product Name',
        'price' => 99.99,
        'quantity' => 1,
    ]);
```

### Headings
- `##` for main sections
- `###` for subsections
- `####` sparingly for deep nesting
- Never skip heading levels

### Links
Cross-reference related documentation:
```md
See the [configuration](configuration) documentation for details.
For panel setup, visit the [introduction/installation](../introduction/installation).
```

## Package Documentation Structure

Each package must have a `docs/` folder with:

1. **01-overview.md** - What it does, key features
2. **02-installation.md** - Composer, config, migrations
3. **03-configuration.md** - All config options explained
4. **04-usage.md** - Basic usage patterns
5. **Feature docs** - One file per major feature (numbered)
6. **99-troubleshooting.md** - Common issues and solutions

## Hosting on Dedicated Domain

### Option 1: Separate Docs Repository (Filament's Approach)

Create a separate repository for the docs site:

```
commerce-docs/           # Separate repo
├── astro.config.mjs
├── package.json
├── src/
│   ├── content/
│   │   └── docs/        # Markdown copied/synced from main repo
│   └── components/
│       ├── Aside.astro
│       ├── AutoScreenshot.astro
│       └── Disclosure.astro
└── scripts/
    └── sync-docs.js     # Script to pull docs from main repo
```

### Option 2: Monorepo Subfolder

Keep docs site in the main repo:

```
commerce/
├── packages/
├── docs-site/           # Astro project
│   ├── astro.config.mjs
│   ├── src/content/docs/
│   └── scripts/sync-docs.js
└── ...
```

### Setup Steps

```bash
# Create docs site (in separate repo or subfolder)
npm create astro@latest docs-site -- --template starlight

cd docs-site

# Configure astro.config.mjs
```

```js
// astro.config.mjs
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
  site: 'https://docs.commerce.dev',
  integrations: [
    starlight({
      title: 'Commerce Docs',
      social: { github: 'https://github.com/AIArmada/commerce' },
      sidebar: [
        { label: 'Getting Started', autogenerate: { directory: 'getting-started' } },
        { label: 'Cart', autogenerate: { directory: 'cart' } },
        { label: 'Cashier', autogenerate: { directory: 'cashier' } },
        { label: 'Chip', autogenerate: { directory: 'chip' } },
        { label: 'Vouchers', autogenerate: { directory: 'vouchers' } },
      ],
    }),
  ],
});
```

### Sync Script

```js
// scripts/sync-docs.js
const fs = require('fs');
const path = require('path');

const MAIN_REPO = process.env.COMMERCE_REPO || '../commerce';
const DEST = path.join(__dirname, '../src/content/docs');

const packages = [
  'cart', 'cashier', 'cashier-chip', 'chip', 
  'vouchers', 'inventory', 'stock', 'docs'
];

// Clean destination
fs.rmSync(DEST, { recursive: true, force: true });
fs.mkdirSync(DEST, { recursive: true });

// Copy package docs
packages.forEach(pkg => {
  const src = path.join(MAIN_REPO, 'packages', pkg, 'docs');
  const dest = path.join(DEST, pkg);
  if (fs.existsSync(src)) {
    fs.cpSync(src, dest, { recursive: true });
    console.log(`✓ Copied ${pkg}/docs`);
  }
});

console.log('Docs synced!');
```

### Deployment

| Platform | Setup |
|----------|-------|
| **Vercel** | Connect repo → Auto-detects Astro → Deploy |
| **Netlify** | Build: `npm run build`, Publish: `dist` |
| **Cloudflare Pages** | Build: `npm run build`, Output: `dist` |
| **GitHub Pages** | Use GitHub Actions with `withastro/action@v3` |

### GitHub Actions (for separate repo)

```yaml
# .github/workflows/deploy.yml
name: Deploy Docs

on:
  push:
    branches: [main]
  repository_dispatch:
    types: [docs-update]  # Triggered from main repo

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Clone main repo for docs
        run: |
          git clone --depth 1 https://github.com/AIArmada/commerce.git ../commerce
          node scripts/sync-docs.js
      
      - uses: withastro/action@v3
```

### Domain Configuration

1. Add custom domain in hosting platform dashboard
2. Configure DNS:
   ```
   CNAME docs.commerce.dev → your-site.vercel.app
   ```
3. HTTPS is automatic on all major platforms

## Verification

```bash
# Check all packages have required docs
for pkg in cart cashier chip vouchers; do
  ls packages/$pkg/docs/01-*.md 2>/dev/null || echo "Missing: $pkg"
done

# Validate frontmatter exists
grep -L "^---" packages/*/docs/*.md

# Check for numbered prefixes
ls packages/*/docs/*.md | grep -v "/[0-9][0-9]-"
```

## Content Checklist

- [ ] Every config key has documentation
- [ ] Every public method has examples  
- [ ] Every event is documented
- [ ] Breaking changes have migration guides
- [ ] Files use numbered prefixes for ordering
- [ ] All files have frontmatter with `title:`
