---
title: Full-Spectrum Package Audit
type: task
priority: critical
status: completed
created: 2025-12-12
last_updated: 2025-12-13
---

# 🔥 Commerce Packages Full-Spectrum Audit Task

This is a comprehensive audit task for all 33 packages in the commerce monorepo, following the **Auditor Agent** specifications with zero-tolerance, enterprise-grade quality standards.

## 📦 Packages to Audit

### Core Packages (16)
| Package | Type | Priority |
|---------|------|----------|
| `affiliates` | Core | High |
| `cart` | Core | Critical |
| `cashier` | Core | Critical |
| `cashier-chip` | Integration | High |
| `chip` | Core | Critical |
| `commerce-support` | Core | Critical |
| `customers` | Core | High |
| `docs` | Documentation | Medium |
| `inventory` | Core | High |
| `jnt` | Integration | Medium |
| `orders` | Core | Critical |
| `pricing` | Core | High |
| `products` | Core | Critical |
| `shipping` | Core | High |
| `tax` | Core | High |
| `vouchers` | Core | High |

### Filament Packages (16)
| Package | Type | Priority |
|---------|------|----------|
| `filament-affiliates` | Admin | Medium |
| `filament-authz` | Admin | High |
| `filament-cart` | Admin | Medium |
| `filament-cashier` | Admin | Medium |
| `filament-cashier-chip` | Admin | Medium |
| `filament-chip` | Admin | Medium |
| `filament-customers` | Admin | Medium |
| `filament-docs` | Admin | Medium |
| `filament-inventory` | Admin | Medium |
| `filament-jnt` | Admin | Low |
| `filament-orders` | Admin | Medium |
| `filament-pricing` | Admin | Low |
| `filament-products` | Admin | Medium |
| `filament-shipping` | Admin | Medium |
| `filament-tax` | Admin | Low |
| `filament-vouchers` | Admin | Medium |

### Other (1)
| Package | Type | Priority |
|---------|------|----------|
| `csuite` | Utility | Low |

---

## 🎯 Audit Scope Per Package

For each package, the audit MUST cover ALL sections below:

### Section 1: Code Quality & Correctness

#### 1A. Code Correctness & Logic
- [ ] Wrong conditions, flawed flow, logic bugs
- [ ] Incorrect branching and return values
- [ ] Dead code, unreachable logic
- [ ] Race conditions
- [ ] Side effects, hidden state

#### 1B. Completeness
- [ ] Missing validations and sanitization
- [ ] Missing error/exception handling
- [ ] Missing boundary checking
- [ ] Missing input/output schema definitions
- [ ] Missing fallbacks & retries

#### 1C. Architecture & Structure
- [ ] SOLID principles compliance
- [ ] Layer boundaries (controller-service-repository)
- [ ] Domain modeling consistency
- [ ] Circular dependencies detection
- [ ] God classes identification
- [ ] Duplicate logic across modules

#### 1D. Performance
- [ ] N+1 queries
- [ ] Inefficient loops and algorithms
- [ ] Excessive memory allocations
- [ ] Duplicate queries
- [ ] Wrong caching strategy
- [ ] Unbatched updates

#### 1E. Security
- [ ] SQL injection vulnerabilities
- [ ] XSS vulnerabilities
- [ ] CSRF protection
- [ ] Missing authorization checks
- [ ] Hardcoded secrets
- [ ] Sensitive data leaks
- [ ] Logging sensitive data

#### 1F. Error Handling & Resiliency
- [ ] Missing try/catch blocks
- [ ] Silent failures
- [ ] No retry strategy
- [ ] No fallback behavior
- [ ] Missing transaction wrapping

#### 1G. Consistency & Maintainability
- [ ] Inconsistent naming
- [ ] Duplicate logic
- [ ] Mixed coding styles
- [ ] Hardcoded magic values
- [ ] Proper documentation

#### 1H. Testing
- [ ] Missing unit tests
- [ ] Missing feature tests
- [ ] No edge case testing
- [ ] No negative testing
- [ ] Coverage >= 85% (non-Filament packages)

### Section 2: Database Audit

#### 2A. Database Modeling & Normalization
- [ ] Proper normalization (1NF → BCNF)
- [ ] No redundant columns
- [ ] Correct datatype selection
- [ ] Proper relational mapping
- [ ] Correct use of JSON fields

#### 2B. Primary Keys & Foreign Keys
- [ ] All tables use `uuid('id')->primary()`
- [ ] All FKs use `foreignUuid()` WITHOUT `constrained()`
- [ ] NO DB-level cascades (application handles these)
- [ ] Consistent constraint naming

#### 2C. Indexing Strategy
- [ ] No missing indexes
- [ ] No over-indexing
- [ ] Correct composite index order
- [ ] Unique indexes where needed

#### 2D. Query Optimization
- [ ] ORM queries are efficient
- [ ] No unnecessary nested SELECTs
- [ ] Proper pagination
- [ ] Query caching where applicable

#### 2E. Migrations & Schema
- [ ] Safe migration patterns
- [ ] Proper `down()` logic
- [ ] No wrong default values
- [ ] JSON columns have `json_column_type` config

### Section 3: Laravel & Commerce-Specific

#### 3A. Eloquent & ORM
- [ ] Eager loading present (no N+1)
- [ ] Correct relationship definitions
- [ ] `$fillable` matches migration columns
- [ ] Proper `$casts` usage
- [ ] Model events in `booted()` for cascades
- [ ] `HasUuids` trait used
- [ ] `getTable()` from config (no `$table` property)
- [ ] PHPDoc `@property` annotations
- [ ] Type-safe relations with generics

#### 3B. Service Providers & Config
- [ ] No unused config keys
- [ ] Config follows standard order
- [ ] Correct service bindings
- [ ] Missing config publishing

#### 3C. Package Architecture
- [ ] Package works standalone
- [ ] Uses `suggest` vs `require` correctly
- [ ] Conditional integrations via `class_exists()`
- [ ] Correct service provider registration

#### 3D. Multitenancy (if applicable)
- [ ] Uses `HasOwner` trait from commerce-support
- [ ] `owner_type` and `owner_id` in fillables and migration
- [ ] Queries use `forOwner()` scope

#### 3E. Documentation
- [ ] `docs/` folder exists with proper structure
- [ ] Files use numbered prefixes (`01-`, `02-`)
- [ ] All files have YAML frontmatter with `title:`
- [ ] Every config key documented
- [ ] Working code examples

#### 3F. Filament Resources (Filament packages only)
- [ ] Form validations present
- [ ] Table columns defined correctly
- [ ] Authorization policies implemented
- [ ] Efficient table queries
- [ ] Bulk actions where appropriate

---

## 📋 Audit Execution Workflow

### Phase 1: Per-Package Audit
For each package, execute in order:

```bash
# 1. View package structure
ls -la packages/<package>/

# 2. Check models
ls packages/<package>/src/Models/

# 3. Check migrations
ls packages/<package>/database/migrations/

# 4. Check config
cat packages/<package>/config/*.php

# 5. Run PHPStan
./vendor/bin/phpstan analyse --level=6 packages/<package>

# 6. Run tests
./vendor/bin/pest tests/src/<PackageName> --parallel

# 7. Check coverage (if .xml exists)
./vendor/bin/phpunit .xml/<package>.xml --coverage

# 8. Run Pint (format check only)
./vendor/bin/pint packages/<package> --test
```

### Phase 2: Issue Documentation
For EVERY issue found, document:

```markdown
### Issue: [Title]
- **File/Component**: path/to/file.php
- **Location**: Lines X-Y / Table / Column
- **Code Snippet**: 
```
[problematic code]
```
- **Why Wrong**: [explanation]
- **Severity**: Low / Medium / High / Critical
- **Fix**:
```
[corrected code]
```
```

### Phase 3: Automatic Repair
After documenting, apply fixes:
- [ ] Corrected code files
- [ ] Improved database schema
- [ ] Rewritten queries
- [ ] New/improved tests
- [ ] Updated migrations
- [ ] Index recommendations applied

### Phase 4: Verification
Run all verification commands:

```bash
# Per-package PHPStan
./vendor/bin/phpstan analyse --level=6 packages/<package>

# Per-package tests
./vendor/bin/pest tests/src/<PackageName> --parallel

# Per-package Pint
./vendor/bin/pint packages/<package>
```

---

## 📊 Audit Report Template

Create audit report at: `packages/<package>/docs/vision/AUDIT.md`

```markdown
---
title: Package Audit Report
audited: YYYY-MM-DD
status: passed | failed | pending
---

# <Package> Audit Report

## Summary
- Total Issues Found: X
- Critical: X
- High: X
- Medium: X
- Low: X
- All Fixed: Yes/No

## Issues Found & Fixed
[List all issues with fixes]

## Verification Results
- PHPStan Level 6: ✅ PASSED / ❌ FAILED
- Tests: ✅ PASSED / ❌ FAILED
- Coverage: XX% (target: 85%)
- Pint: ✅ PASSED / ❌ FAILED

## Recommendations
[Any remaining improvements]
```

---

## 🚀 Execution Order

Process packages in this priority order:

### Critical Priority (Do First)
1. `commerce-support` - Foundation for all packages
2. `chip` - Payment core
3. `cashier` - Payment integration
4. `cart` - Shopping cart core
5. `products` - Product management
6. `orders` - Order management

### High Priority
7. `affiliates`
8. `customers`
9. `inventory`
10. `shipping`
11. `tax`
12. `vouchers`
13. `cashier-chip`
14. `pricing`
15. `filament-authz`

### Medium Priority
16. `filament-cart`
17. `filament-cashier`
18. `filament-cashier-chip`
19. `filament-chip`
20. `filament-customers`
21. `filament-docs`
22. `filament-inventory`
23. `filament-orders`
24. `filament-products`
25. `filament-shipping`
26. `filament-vouchers`
27. `filament-affiliates`
28. `jnt`
29. `docs`

### Low Priority
30. `filament-jnt`
31. `filament-pricing`
32. `filament-tax`
33. `csuite`

---

## ✅ Completion Criteria

A package audit is complete when:

1. ✅ All audit checklist items reviewed
2. ✅ All issues documented in AUDIT.md
3. ✅ All critical/high issues fixed
4. ✅ PHPStan level 6 passes
5. ✅ All tests pass
6. ✅ Coverage >= 85% (non-Filament)
7. ✅ Pint passes
8. ✅ PROGRESS.md updated (if exists)

---

## 📝 Notes

- **Never run PHPStan on whole packages directory** - always per-package
- **Never run full test suite** - always per-package with `--parallel`
- **Backup files before destructive changes**
- **Follow existing code conventions in each package**
- **DTOs must use Laravel Data**
- **No DB-level FK constraints or cascades**

---

## 🔗 References

- [Copilot Instructions](/.github/copilot-instructions.md)
- [Auditor Agent](/.github/agents/Auditor.agent.md)
- [GEMINI.md](/.ai/GEMINI.md)
