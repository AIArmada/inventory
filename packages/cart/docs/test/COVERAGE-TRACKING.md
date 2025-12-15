# Cart Package Coverage Tracking

**Current Coverage:** 67.5%  
**Target Coverage:** 80%  
**Last Updated:** 2025-12-16T05:32:37+08:00

---

## 0% Coverage Files (HIGHEST PRIORITY)

These files have no test coverage and should be addressed first:

| File | Priority | Notes |
|------|----------|-------|
| `Events/Store/EloquentCartEventRepository` | HIGH | Event storage repository |
| `GraphQL/Mutations/CartMutations` | MEDIUM | GraphQL mutations (may be excluded if no GraphQL usage) |
| `GraphQL/Queries/CartQuery` | MEDIUM | GraphQL queries (may be excluded if no GraphQL usage) |
| `GraphQL/Subscriptions/CartSubscription` | MEDIUM | GraphQL subscriptions (may be excluded if no GraphQL usage) |
| `GraphQL/Types/CartType` | MEDIUM | GraphQL types (may be excluded if no GraphQL usage) |

---

## Low Coverage Files (<30%)

These files have very low coverage and require immediate attention:

| File | Current Coverage | Missing Lines | Priority |
|------|-----------------|---------------|----------|
| `Jobs/ExecuteRecoveryIntervention` | 1.4% | 59..108, 93..234 | HIGH |
| `Security/Fraud/Detectors/VelocityAnalyzer` | 6.0% | 64..483, 79..326 | HIGH |
| `Events/MetadataBatchAdded` | 6.3% | 44..88 | HIGH |
| `Jobs/AnalyzeCartForAbandonment` | 6.2% | 59..65, 89..188 | HIGH |
| `Conditions/Pipeline/Resolvers/FulfillmentScopeResolver` | 7.7% | 19..48 | HIGH |
| `Security/Fraud/Detectors/PriceManipulationDetector` | 8.7% | 62..77, 97..313, 75..280 | HIGH |
| `Events/Concerns/HasCartEventData` | 9.5% | 31..109, 85..92 | HIGH |
| `Jobs/WarmCartCacheJob` | 10.0% | 45..67, 62..68 | HIGH |
| `Security/Fraud/FraudDetectionEngine` | 20.0% | 72..130, 160..214, 74..112 | HIGH |
| `Conditions/Pipeline/Resolvers/DefaultScopeResolver` | 25.0% | 27..32 | HIGH |
| `Projectors/CartProjector` | 27.3% | 50..129 | HIGH |
| `Security/Fraud/FraudSignalCollector` | 27.0% | 45..63, 75..381 | HIGH |

---

## Medium Coverage Files (30-60%)

These files need additional tests to reach 80%:

| File | Current Coverage | Missing Lines |
|------|-----------------|---------------|
| `Traits/HasLazyPipeline` | 41.4% | 28..42, 74..82, 103..107, 116, 128, 75..79 |
| `Conditions/TargetPresets` | 46.2% | 35..54, 67..70, 89..97 |
| `Examples/ExampleRulesFactory` | 51.3% | 45..51, 57..61, 68..72, 78..91, 97..106, 119..120 |
| `Conditions/Pipeline/Resolvers/ShipmentScopeResolver` | 53.8% | 14, 40..48 |
| `Events/Store/CartEventRecorder` | 54.8% | 38..48, 64..79, 94, 112, 128 |
| `Traits/ProvidesConditionScopes` | 55.6% | 37, 70, 86..107 |
| `Conditions/Pipeline/Resolvers/PaymentScopeResolver` | 57.1% | 14, 41..49 |
| `Services/TaxCalculator` | 58.6% | 188..190, 216..272, 314..326 |
| `Http/Middleware/ThrottleCartOperations` | 59.6% | 42..44, 61, 70, 84..112 |
| `Events/CartCleared` | 60.0% | 56..80 |
| `Events/CartCreated` | 60.0% | 53..77 |
| `Testing/InMemoryStorage` | 60.4% | 51..55, 142..160, 179..187, 250..464 |
| `Storage/DatabaseStorage` | 60.9% | 147..159, 183..188, 222, 282..286, 359..835 |
| `ReadModels/CartReadModel` | 63.1% | 57, 84..97, 122..145, 173..330 |
| `Events/CartDestroyed` | 63.6% | 60..84 |
| `Conditions/Target` | 64.5% | 13, 53..65, 57..66 |
| `Traits/ManagesBuyables` | 68.1% | 170..234, 222 |
| `Services/BuiltInRulesFactory` | 68.6% | 77, 79..80, 87..90, 92..93...many lines |
| `Events/ItemAdded` | 71.4% | 60..84 |
| `Events/ItemRemoved` | 71.4% | 60..84 |
| `Events/ItemUpdated` | 71.4% | 60..84 |
| `Events/MetadataCleared` | 71.4% | 45..69 |
| `Security/CartRateLimiter` | 72.5% | 74..78, 103..106, 118..121, 133, 148..168 |
| `Events/MetadataRemoved` | 73.3% | 47..71 |
| `Events/MetadataAdded` | 75.0% | 49..73 |

---

## Files Above 80% (OK)

These files are meeting the target. No changes needed unless they drop below:

- All files at 100%
- `Conditions/Pipeline/LazyConditionPipeline` (83.5%)
- `Console/Commands/ClearAbandonedCartsCommand` (85.4%)
- `Traits/CalculatesTotals` (85.7%)
- `Services/CartConditionResolver` (86.7%)
- `Services/CartMigrationService` (87.1%)
- `Infrastructure/Caching/CachedCartRepository` (87.5%)
- `Events/ItemConditionAdded` (87.5%)
- `Events/ItemConditionRemoved` (88.6%)
- `Traits/ManagesMetadata` (90.5%)
- `Traits/ManagesStorage` (90.6%)
- `Models/Condition` (91.8%)
- `Infrastructure/Caching/CartCacheInvalidator` (93.1%)
- `Traits/HasRateLimiting` (93.8%)
- `Conditions/Pipeline/Resolvers/AbstractDatasetResolver` (96.2%)
- `Traits/ManagesInstances` (96.6%)
- `Services/ShippingCalculator` (97.0%)
- `Traits/ManagesDynamicConditions` (98.2%)
- `Traits/ManagesItems` (99.3%)
- `Traits/ManagesConditions` (99.5%)
- `Storage/SessionStorage` (81.2%)
- `Traits/ImplementsCheckoutable` (81.6%)
- `Events/CartConditionAdded` (82.6%)
- `Events/CartMerged` (84.0%)
- `Events/CartConditionRemoved` (84.6%)
- `Conditions/Pipeline/Resolvers/CartScopeResolver` (80.0%)

---

## Testing Strategy

### Phase 1: Eliminate 0% Files
1. Create tests for `EloquentCartEventRepository`
2. Evaluate if GraphQL files need testing (check if used in app)

### Phase 2: Low Coverage Files (<30%)
1. `Jobs/*` - Test job execution and edge cases
2. `Security/Fraud/*` - Test fraud detection logic
3. `Projectors/CartProjector` - Test event projection
4. `Conditions/Pipeline/Resolvers/*` - Test scope resolvers

### Phase 3: Medium Coverage Files (30-60%)
1. `Services/TaxCalculator` - Complete tax calculation tests
2. `Storage/DatabaseStorage` - Complete storage tests
3. `Events/*` - Add event serialization tests
4. `Traits/*` - Add trait method tests

---

## Progress Log

| Date | Coverage Before | Coverage After | Files Improved |
|------|-----------------|----------------|----------------|
| 2025-12-16 | 67.5% | - | Initial tracking |

