# Tests Passed: 1.2.0

**Date:** 2026-11-11T16:45:34Z
**Commit:** 91934a0a27ec127e7a91927a26561f404be6e6f4
**Tracked changes at run time:** 0

| Gate | Result |
|---|---|
| composer install | pass |
| composer validate --strict | pass |
| composer run check | pass |
| version consistency | pass |
| build (composer run build) | pass |
| Plugin Check | not run locally (no plugin-check script); CI runs it on the built zip |
| PHPUnit (npm test) | pass |

PHPUnit summaries:

    OK (133 tests, 191 assertions)

Full output: tests.log (not committed).
