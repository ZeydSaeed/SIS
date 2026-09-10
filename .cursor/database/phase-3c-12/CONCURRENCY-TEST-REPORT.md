# CONCURRENCY TEST REPORT

| Scenario | Physical control | Runtime stress test |
|----------|------------------|---------------------|
| Dual version_no | UNIQUE (parent, version_no) | Not executed (constraint present) |
| Dual current official | Partial UNIQUE | Not executed (constraint present) |
| Dual current issued | Partial UNIQUE | Not executed (constraint present) |
| Dual approval attempt | UNIQUE (version, attempt) | Not executed (constraint present) |
| Idempotency conflict | Fingerprint guard | Unit PASS |

```text
STATUS: PASS WITH CONDITIONS — invariants in DB; concurrent load tests deferred
```
