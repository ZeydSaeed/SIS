# PHASE TR — TRANSFERS
# HUMAN SUBPHASE START AUTHORIZATION + PATH SELECTION

---

```text
Date: 2026-09-12
Human: «استمر واختار الافضل»
Selected: Phase TR — Transfers (schema + request lifecycle HTTP)
Rejected: Ranking/PDF portal (staff Ranking/Transcript HTTP already LIVE; PDF deferred by 7.5 lock)
Rejected: CompleteTransfer (dual enrollment mutate) in v1
Status: GRANTED
```

## Why Transfers (not Ranking/PDF)

| Criterion | Transfers | Ranking/PDF portal |
|-----------|-----------|--------------------|
| Gap | Tables ABSENT | Staff HTTP already exists (7.5/7.6) |
| PDF | N/A | Explicitly DEFERRED (HD-7.5-010) |
| Portal ranking | N/A | OUT of 7.8 lock |
| Lifecycle | Next after Promotion | Already covered staff-side |
| Risk if rushed | Cross-school RLS | Inventing PDF engine |

```text
Best path = close the empty Transfers lifecycle with fail-closed dual-school RLS,
without CompleteTransfer enrollment mutation in v1.
```
