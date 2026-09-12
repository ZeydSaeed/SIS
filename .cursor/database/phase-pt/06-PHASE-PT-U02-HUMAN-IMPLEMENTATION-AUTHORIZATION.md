# PHASE PT — U02 HUMAN IMPLEMENTATION AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر»
Unit: PT-U02 — Promotion staff HTTP (rules create/list + record decision + list records)
Status: GRANTED
Schema change: NONE
```

## Scope

```text
IN:
  CreatePromotionRule (idempotent)
  ListPromotionRules
  RecordPromotionDecision (idempotent) — no enrollment mutation
  ListPromotionRecords
  Permissions: promotion.view / promotion.manage
OUT:
  Auto GPA eligibility
  Transfers
  Deactivate rule HTTP (can use is_active on create; deactivate later)
```
