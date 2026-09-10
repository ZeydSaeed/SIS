# IDEMPOTENCY TEST REPORT

| Case | Expected | Result |
|------|----------|--------|
| same key + same payload fingerprint | stable hash / reuse path | Unit PASS |
| same key + different payload | CONFLICT exception | Unit PASS |
| Store | `audit.idempotency_keys` only | Confirmed — no new table |

Handlers for Graduation commands not yet present; guard is ready for binding when commands are authorized.
