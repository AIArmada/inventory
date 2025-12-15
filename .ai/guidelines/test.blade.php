# Testing Guidelines

**The ultimate goal is to ELIMINATE all bugs.**
Not skipping them. Not avoiding them.
If there is one thing you should be very sensitive about, it's the bugs.
**FIX THEM LIKE THE WORLD IS GONNA END IF NOT.**

## Filament PHP Testing References

When testing Filament components, refer to these official documentation pages:

| Topic | Documentation URL |
|-------|-------------------|
| Overview | https://filamentphp.com/docs/4.x/testing/overview |
| Testing Resources | https://filamentphp.com/docs/4.x/testing/testing-resources |
| Testing Tables | https://filamentphp.com/docs/4.x/testing/testing-tables |
| Testing Schemas | https://filamentphp.com/docs/4.x/testing/testing-schemas |
| Testing Actions | https://filamentphp.com/docs/4.x/testing/testing-actions |
| Testing Notifications | https://filamentphp.com/docs/4.x/testing/testing-notifications |

**Key Testing Helpers:**
- `Livewire::test()` - For testing Filament pages and components
- `assertCanSeeTableRecords()` - Assert records visible in table
- `assertFormFieldExists()` - Assert form field presence
- `callAction()` - Trigger actions in tests
- `assertNotified()` - Assert notifications were sent

## Core Principle: Targeted Testing First

- **Never run full package tests unnecessarily**; always prefer targeted test execution for specific files or
directories you modified.
- Running full package tests is EXPENSIVE (can take 5-10+ minutes). Reserve it for final verification only.

## ⚠️ MANDATORY: Always Save Test Output

**Every test execution MUST capture output to a temporary file.** This prevents re-running tests just to see error
details.

```bash
# ALWAYS use this pattern - pipe output to tee
./vendor/bin/pest tests/src/Cart/Unit/MyTest.php 2>&1 | tee /tmp/test-output.txt

# For full package runs (rare)
./vendor/bin/pest --parallel tests/src/PackageName 2>&1 | tee /tmp/package-tests.txt

# For coverage runs
./vendor/bin/pest --parallel --coverage --configuration=.xml/package.xml 2>&1 | tee /tmp/coverage.txt
```

**Why this matters:**
- If tests fail, you have the full error output saved
- No need to re-run tests just to see what failed
- Can analyze failures, group by cause, and batch-fix
- Coverage output preserved for identifying low-coverage files

**Naming convention for temp files:**
- Single file test: `/tmp/test-<filename>.txt`
    - Directory test: `/tmp/test-<dirname>.txt`
        - Full package: `/tmp/test-<package>-full.txt`
            - Coverage: `/tmp/coverage-<package>.txt`

                **WARNING: Avoid using `tail` or truncating output if it hinders visibility of all involved files,
                especially for coverage reports. Always ensure you read the full output.**

                ## Targeted Test Execution (Primary Approach)

                When you create, modify, or fix test files, run ONLY those specific files:

                ```bash
                # Run a SINGLE test file (PREFERRED for development)
                ./vendor/bin/pest tests/src/Cart/Unit/MyNewTest.php

                # Run a small directory of related tests
                ./vendor/bin/pest tests/src/Cart/Unit/Security/

                # Run with a filter for specific test names
                ./vendor/bin/pest --filter="test name pattern" tests/src/PackageName
                ```

                ## When to Use `--parallel`

                - `--parallel` MUST be the first argument after `./vendor/bin/pest`.
                - Use `--parallel` ONLY for full package/directory runs, NOT for single file execution.
                - Single file tests are faster without the parallel overhead.

                ```bash
                # Single file (no --parallel needed)
                ./vendor/bin/pest tests/src/Cart/Unit/MyTest.php

                # Full package (use --parallel)
                ./vendor/bin/pest --parallel tests/src/Cart
                ```

                ## Full Package Test Strategy (RESTRICTED)

                Run full package tests ONLY when ALL of these conditions are met:

                1. **High Confidence**: All individual test files pass when run separately
                2. **Near Completion**: Working on final verification before PR/commit
                3. **Coverage Analysis**: Need an actual coverage percentage (do pre-calculation first)
                4. **Pre-Calculation for Coverage Goals**: Before running full coverage, calculate feasibility:

                ### Coverage Feasibility Pre-Check

                Before running a full coverage report, estimate if your goal is achievable:

                ```
                Calculation Formula:
                - Count files at 0% coverage → X files
                - Count total files in package → Y files
                - Zero coverage ratio = X / Y

                If zero coverage ratio > 10%, full coverage run is premature.
                Work on reducing 0% files first with targeted tests.
                ```

                Example: If goal is 90% coverage but 20% of files have 0% coverage, it's mathematically impossible.
                Focus on covering
                those files first.

                ## Test Development Workflow

                1. **Create test file(s)** → Batch multiple tests together before running
                2. **Run ONLY the new file(s)** → `./vendor/bin/pest tests/src/Package/Unit/NewTest.php`
                3. **Fix failures immediately** → Don't accumulate failing tests
                4. **Repeat** → Create more tests, run individually
                5. **Batch verification** → After 5-10 test files, run the directory
                6. **Full package (rare)** → Only for final verification

                ## When Many Failures Occur

                1. **Capture once**: `./vendor/bin/pest tests/src/PackageName 2>&1 | tee failures.txt`
                2. **Group by cause**: Identify common patterns (missing mocks, wrong signatures, etc.)
                3. **Batch-fix**: Fix all similar issues at once
                4. **Rerun targeted files**: `./vendor/bin/pest tests/src/PackageName/Unit/FailingTest.php`
                5. **Only after all pass**: Consider full package run

                ## Coverage Command (Use Sparingly)

                ```bash
                # Full coverage (EXPENSIVE - 5-10+ minutes)
                ./vendor/bin/pest --parallel --coverage --configuration=.xml/package.xml

                # AFTER running coverage, capture the output and extract:
                # - List of 0% coverage files (priority targets)
                # - List of low coverage files (<50%) # - Save this list to avoid re-running coverage just for discovery
                    ``` ## Test File Size Strategy - Create MULTIPLE tests per file to reduce file count - Use
                    `describe()` blocks to group related tests - Aim for 5-20 assertions per test file - This reduces
                    the number of separate test runs needed ## Minimum Coverage Targets - Core packages (cart, vouchers,
                    inventory, etc.): ≥85% - Filament packages: ≥70% (UI-heavy, harder to unit test) - Support packages:
                    ≥80% ## Summary Decision Tree ``` Need to verify test I just wrote? → Run SINGLE FILE only Need to
                    verify a batch of tests I created? → Run DIRECTORY only Need final verification before commit? → Run
                    FULL PACKAGE (rare) Need coverage percentage? → Pre-calculate feasibility first → Only run if goal
                    seems achievable → Save output to avoid re-running for discovery ```