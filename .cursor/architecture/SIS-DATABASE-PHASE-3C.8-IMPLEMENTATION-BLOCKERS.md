# SIS DATABASE — PHASE 3C.8  
# IMPLEMENTATION BLOCKERS & CLOSURE ORDER

**Document type:** GOVERNANCE  
**Date:** 2026-09-10  

```text
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
Even after HD closure, a separate human approval is required to start schema/code.
```

---

## 1. Blocker Matrix

Legend: **BLOCKED** | **NOT_BLOCKED** | **CONDITIONAL**

| Decision | Schema | Migration | Engine | CQRS | Approval | Publication | StudentStatus | Security | Can Defer? |
|----------|--------|-----------|--------|------|----------|-------------|---------------|----------|------------|
| DL-017…022 acceptance | BLOCKED until accept | BLOCKED | BLOCKED | BLOCKED | BLOCKED | BLOCKED | BLOCKED | CONDITIONAL | **No** (conceptual) |
| HD-19 | BLOCKED | BLOCKED | BLOCKED | CONDITIONAL | CONDITIONAL | NOT_BLOCKED | CONDITIONAL | NOT_BLOCKED | No |
| HD-20 | CONDITIONAL | CONDITIONAL | BLOCKED | CONDITIONAL | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | No for engine |
| HD-21 | CONDITIONAL | CONDITIONAL | BLOCKED | CONDITIONAL | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | No for engine |
| HD-22 | CONDITIONAL | CONDITIONAL | BLOCKED if GPA-gated | CONDITIONAL | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | Yes if no GPA gate |
| HD-23…30 | NOT_BLOCKED | NOT_BLOCKED | BLOCKED if category used | CONDITIONAL | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | Yes if category unused |
| HD-31 | CONDITIONAL | CONDITIONAL | NOT_BLOCKED | BLOCKED (Approve*) | BLOCKED | CONDITIONAL | NOT_BLOCKED | BLOCKED (authz) | No for approval |
| HD-32 | CONDITIONAL | CONDITIONAL | NOT_BLOCKED | BLOCKED (Award*) | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL | No for award |
| HD-33/34 | CONDITIONAL | CONDITIONAL | NOT_BLOCKED | CONDITIONAL | CONDITIONAL | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | Soft defer risky |
| HD-35 | BLOCKED | BLOCKED | BLOCKED | BLOCKED | BLOCKED | CONDITIONAL | BLOCKED | CONDITIONAL | **No** |
| HD-36 | BLOCKED | BLOCKED | BLOCKED | BLOCKED | BLOCKED | CONDITIONAL | BLOCKED | CONDITIONAL | **No** |
| HD-37 | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | **Yes** |
| HD-38 | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | BLOCKED (Publish*) | NOT_BLOCKED | BLOCKED | NOT_BLOCKED | CONDITIONAL | Yes until publish |
| HD-39 | BLOCKED | BLOCKED | BLOCKED | CONDITIONAL | CONDITIONAL | NOT_BLOCKED | CONDITIONAL | BLOCKED (scope) | No |
| HD-40 | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | CONDITIONAL | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | Soft — confirm early |
| HD-41 | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | CONDITIONAL | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | Soft — confirm early |
| HD-42 | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | **Yes** |
| HD-01/15/Rounding | NOT_BLOCKED | NOT_BLOCKED | BLOCKED if GPA evidence | CONDITIONAL | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | Yes if no GPA |
| HD-04…07/18 | NOT_BLOCKED | NOT_BLOCKED | BLOCKED for official eval | CONDITIONAL | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | NOT_BLOCKED | No for official |
| Security/RLS design | NOT_BLOCKED (design) | BLOCKED at migrate | BLOCKED at runtime | BLOCKED | BLOCKED | BLOCKED | BLOCKED | BLOCKED at impl | Design done; impl later |

---

## 2. Absolute Implementation Blocks (now)

```text
BLOCK implementation of graduation/completion schema, migration, engine, and approval workflows until:

1. Human ACCEPT (or modify) DL-017…DL-022
2. HD-19 closed (or APPROVED_WITH_CONDITION on arch distinction)
3. HD-39 closed for identity scope
4. HD-35 and HD-36 closed (correction/revocation) — prevent silent rewrite
5. Separate explicit implementation authorization phase/gate
```

Evaluation engine additionally blocked on HD-20/21 (+ required 22–30) and needed upstream Results HDs.

---

## 3. Closure Order (derived)

```text
P0-01  Human ACCEPT DL-017…DL-022
   ↓
P0-02  HD-19  Completion ≠ Graduation (confirm)
   ↓
P0-03  HD-39  Multi-program / identity scope
   ↓
P0-04  HD-35  Correction after graduation
   ↓
P0-05  HD-36  Revocation (with or immediately after HD-35)

parallel (after P0-01…03):
P0-06  HD-20  Eligibility policy framework
P0-07  HD-31  Approval authority
P0-08  HD-32  Award semantics

then:
P0-09  HD-21 (+ HD-22…30 as required by HD-20)
P1-01  HD-33 / HD-34 date semantics
P1-02  HD-40 / HD-41 confirm independent / separate
P1-03  Upstream HD-04…07/18 (and HD-01/15 if GPA-gated)

then (still needs separate auth):
   Logical schema design authorization
   Physical schema / migration authorization
   Evaluation engine authorization
   Approval/award CQRS authorization

P1-04  HD-38 before publication features
P2-01  HD-37 retention years (ops/legal)
P2-02  HD-42 packaging / HD-16 transcript packaging
```

---

## 4. What May Remain Deferred

| Item | Why safe to defer |
|------|-------------------|
| HD-37 retention years | Default no hard-delete already; duration is ops/legal |
| HD-42 / HD-16 packaging | Not academic truth |
| Ranking HDs | Default not coupled to graduation |
| Unused requirement categories (27–30) | Until HD-20 selects them |
| UI copy / report MV preferences | Non-authoritative |

---

## 5. Next Phase Recommendation (not auto-start)

1. **Human Decision Workshop** — Packs A–D in Human Decision Closure doc  
2. On acceptance → **Phase 3C.9 (proposed): Logical Schema Authorization Gate** (docs only until approved)  
3. **Do not** start migrations/code without new explicit authorization  

```text
STOP — no Phase 3C.9 auto-start from this document alone
```
